const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');
const cors = require('cors');
const jwt = require('jsonwebtoken');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

// MySQL Connection Pool
const pool = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'whatsapp_clone',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// Store online users
const onlineUsers = new Map(); // userId -> socketId
const userSockets = new Map(); // socketId -> userId

// JWT Secret
const JWT_SECRET = 'your-secret-key-change-this';

// Middleware to authenticate socket connections
io.use(async (socket, next) => {
  const token = socket.handshake.auth.token;
  if (!token) {
    return next(new Error('Authentication error'));
  }

  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    socket.userId = decoded.userId;
    next();
  } catch (err) {
    next(new Error('Invalid token'));
  }
});

// Socket.IO Connection Handler
io.on('connection', (socket) => {
  console.log(`User connected: ${socket.userId}`);
  
  // Store user connection
  onlineUsers.set(socket.userId, socket.id);
  userSockets.set(socket.id, socket.userId);
  
  // Update user status to online
  updateUserStatus(socket.userId, 'online');
  
  // Broadcast online status to contacts
  broadcastUserStatus(socket.userId, 'online');
  
  // Join user to their personal room
  socket.join(`user:${socket.userId}`);
  
  // Handle sending message
  socket.on('send-message', async (data) => {
    try {
      const { chatId, message, replyToId } = data;
      const senderId = socket.userId;
      
      // Save message to database
      const [result] = await pool.execute(
        `INSERT INTO messages (chat_id, sender_id, message, reply_to_id) 
         VALUES (?, ?, ?, ?)`,
        [chatId, senderId, message, replyToId || null]
      );
      
      const messageId = result.insertId;
      
      // Get the created message with user details
      const [rows] = await pool.execute(
        `SELECT m.*, u.full_name, u.username, u.profile_pic 
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE m.id = ?`,
        [messageId]
      );
      
      const newMessage = rows[0];
      
      // Update chat's last message
      await pool.execute(
        `UPDATE chats SET last_message_id = ?, updated_at = NOW() WHERE id = ?`,
        [messageId, chatId]
      );
      
      // Get chat participants
      const [chatRows] = await pool.execute(
        `SELECT user1_id, user2_id FROM chats WHERE id = ?`,
        [chatId]
      );
      
      if (chatRows.length > 0) {
        const chat = chatRows[0];
        const receiverId = chat.user1_id == senderId ? chat.user2_id : chat.user1_id;
        
        // Emit to sender (for immediate display)
        socket.emit('message-sent', { 
          success: true, 
          message: newMessage,
          chatId 
        });
        
        // Emit to receiver if online
        const receiverSocketId = onlineUsers.get(receiverId);
        if (receiverSocketId) {
          io.to(receiverSocketId).emit('new-message', {
            chatId,
            message: newMessage
          });
          
          // Update message status to delivered
          await pool.execute(
            `UPDATE messages SET is_delivered = 1 WHERE id = ?`,
            [messageId]
          );
        }
        
        // Emit to both users to update chat list
        io.to(`user:${senderId}`).emit('chat-updated', { chatId });
        io.to(`user:${receiverId}`).emit('chat-updated', { chatId });
      }
      
    } catch (error) {
      console.error('Error sending message:', error);
      socket.emit('message-error', { error: 'Failed to send message' });
    }
  });
  
  // Handle typing indicator
  socket.on('typing', async (data) => {
    const { chatId, isTyping } = data;
    
    // Get chat participants
    const [rows] = await pool.execute(
      `SELECT user1_id, user2_id FROM chats WHERE id = ?`,
      [chatId]
    );
    
    if (rows.length > 0) {
      const chat = rows[0];
      const receiverId = chat.user1_id == socket.userId ? chat.user2_id : chat.user1_id;
      
      const receiverSocketId = onlineUsers.get(receiverId);
      if (receiverSocketId) {
        io.to(receiverSocketId).emit('user-typing', {
          chatId,
          userId: socket.userId,
          isTyping
        });
      }
    }
  });
  
  // Handle mark as read
  socket.on('mark-read', async (data) => {
    try {
      const { chatId, messageIds } = data;
      
      if (messageIds && messageIds.length > 0) {
        await pool.execute(
          `UPDATE messages SET is_read = 1 
           WHERE id IN (?) AND sender_id != ?`,
          [messageIds, socket.userId]
        );
        
        // Get chat participants
        const [rows] = await pool.execute(
          `SELECT user1_id, user2_id FROM chats WHERE id = ?`,
          [chatId]
        );
        
        if (rows.length > 0) {
          const chat = rows[0];
          const otherUserId = chat.user1_id == socket.userId ? chat.user2_id : chat.user1_id;
          
          const otherSocketId = onlineUsers.get(otherUserId);
          if (otherSocketId) {
            io.to(otherSocketId).emit('messages-read', {
              chatId,
              userId: socket.userId,
              messageIds
            });
          }
        }
      }
    } catch (error) {
      console.error('Error marking as read:', error);
    }
  });
  
  // Handle create chat
  socket.on('create-chat', async (data) => {
    try {
      const { otherUserId } = data;
      const userId = socket.userId;
      
      // Check if chat exists
      const [existing] = await pool.execute(
        `SELECT id FROM chats 
         WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)`,
        [userId, otherUserId, otherUserId, userId]
      );
      
      let chatId;
      
      if (existing.length > 0) {
        chatId = existing[0].id;
      } else {
        // Create new chat
        const [result] = await pool.execute(
          `INSERT INTO chats (user1_id, user2_id) VALUES (?, ?)`,
          [userId, otherUserId]
        );
        chatId = result.insertId;
      }
      
      // Get chat details
      const [chatRows] = await pool.execute(
        `SELECT c.*, 
                u1.id as user1_id, u1.full_name as user1_name, u1.username as user1_username, u1.profile_pic as user1_pic, u1.status as user1_status,
                u2.id as user2_id, u2.full_name as user2_name, u2.username as user2_username, u2.profile_pic as user2_pic, u2.status as user2_status
         FROM chats c
         JOIN users u1 ON c.user1_id = u1.id
         JOIN users u2 ON c.user2_id = u2.id
         WHERE c.id = ?`,
        [chatId]
      );
      
      const chat = chatRows[0];
      const partner = chat.user1_id == userId ? 
        { id: chat.user2_id, name: chat.user2_name, username: chat.user2_username, pic: chat.user2_pic, status: chat.user2_status } :
        { id: chat.user1_id, name: chat.user1_name, username: chat.user1_username, pic: chat.user1_pic, status: chat.user1_status };
      
      socket.emit('chat-created', { success: true, chatId, partner });
      
      // Notify other user about new chat
      const otherSocketId = onlineUsers.get(otherUserId);
      if (otherSocketId) {
        const myInfo = await getUserInfo(userId);
        io.to(otherSocketId).emit('new-chat', {
          chatId,
          partner: myInfo
        });
      }
      
    } catch (error) {
      console.error('Error creating chat:', error);
      socket.emit('chat-error', { error: 'Failed to create chat' });
    }
  });
  
  // Handle add reaction
  socket.on('add-reaction', async (data) => {
    try {
      const { messageId, reaction } = data;
      
      // Save reaction to database (you'd need a reactions table)
      // For now, just broadcast
      
      const [rows] = await pool.execute(
        `SELECT chat_id, sender_id FROM messages WHERE id = ?`,
        [messageId]
      );
      
      if (rows.length > 0) {
        const message = rows[0];
        const [chatRows] = await pool.execute(
          `SELECT user1_id, user2_id FROM chats WHERE id = ?`,
          [message.chat_id]
        );
        
        if (chatRows.length > 0) {
          const chat = chatRows[0];
          const otherUserId = chat.user1_id == socket.userId ? chat.user2_id : chat.user1_id;
          
          const otherSocketId = onlineUsers.get(otherUserId);
          if (otherSocketId) {
            io.to(otherSocketId).emit('message-reaction', {
              messageId,
              userId: socket.userId,
              reaction
            });
          }
        }
      }
      
    } catch (error) {
      console.error('Error adding reaction:', error);
    }
  });
  
  // Handle delete message
  socket.on('delete-message', async (data) => {
    try {
      const { messageId, deleteFor } = data;
      
      if (deleteFor === 'everyone') {
        await pool.execute(
          `UPDATE messages SET deleted_for_everyone = 1 WHERE id = ? AND sender_id = ?`,
          [messageId, socket.userId]
        );
      } else {
        await pool.execute(
          `UPDATE messages SET deleted_for_me = 1 WHERE id = ?`,
          [messageId]
        );
      }
      
      // Get chat info to notify others
      const [rows] = await pool.execute(
        `SELECT chat_id, sender_id FROM messages WHERE id = ?`,
        [messageId]
      );
      
      if (rows.length > 0) {
        const message = rows[0];
        const [chatRows] = await pool.execute(
          `SELECT user1_id, user2_id FROM chats WHERE id = ?`,
          [message.chat_id]
        );
        
        if (chatRows.length > 0) {
          const chat = chatRows[0];
          const otherUserId = chat.user1_id == socket.userId ? chat.user2_id : chat.user1_id;
          
          const otherSocketId = onlineUsers.get(otherUserId);
          if (otherSocketId && deleteFor === 'everyone') {
            io.to(otherSocketId).emit('message-deleted', {
              messageId,
              chatId: message.chat_id
            });
          }
        }
      }
      
      socket.emit('message-deleted', { success: true, messageId });
      
    } catch (error) {
      console.error('Error deleting message:', error);
    }
  });
  
  // Handle disconnect
  socket.on('disconnect', () => {
    const userId = userSockets.get(socket.id);
    if (userId) {
      console.log(`User disconnected: ${userId}`);
      
      // Remove from online maps
      onlineUsers.delete(userId);
      userSockets.delete(socket.id);
      
      // Update status to offline
      updateUserStatus(userId, 'offline');
      
      // Broadcast offline status
      broadcastUserStatus(userId, 'offline');
    }
  });
});

// Helper function to update user status in database
async function updateUserStatus(userId, status) {
  try {
    await pool.execute(
      `UPDATE users SET status = ?, last_seen = NOW() WHERE id = ?`,
      [status, userId]
    );
  } catch (error) {
    console.error('Error updating user status:', error);
  }
}

// Helper function to broadcast user status to contacts
async function broadcastUserStatus(userId, status) {
  try {
    // Get user's contacts
    const [contacts] = await pool.execute(
      `SELECT contact_id FROM contacts WHERE user_id = ?`,
      [userId]
    );
    
    // Get user info
    const [userRows] = await pool.execute(
      `SELECT id, full_name, username, profile_pic FROM users WHERE id = ?`,
      [userId]
    );
    
    if (userRows.length > 0) {
      const userInfo = userRows[0];
      
      // Notify all contacts
      for (const contact of contacts) {
        const contactSocketId = onlineUsers.get(contact.contact_id);
        if (contactSocketId) {
          io.to(contactSocketId).emit('contact-status', {
            userId,
            status,
            userInfo,
            lastSeen: new Date()
          });
        }
      }
    }
  } catch (error) {
    console.error('Error broadcasting status:', error);
  }
}

// Helper function to get user info
async function getUserInfo(userId) {
  const [rows] = await pool.execute(
    `SELECT id, full_name, username, profile_pic, status FROM users WHERE id = ?`,
    [userId]
  );
  return rows[0];
}

// REST API endpoint for JWT token
app.post('/api/auth/token', async (req, res) => {
  const { userId } = req.body;
  const token = jwt.sign({ userId }, JWT_SECRET, { expiresIn: '7d' });
  res.json({ token });
});

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', onlineUsers: onlineUsers.size });
});

// Get online users count
app.get('/api/stats', (req, res) => {
  res.json({
    onlineUsers: onlineUsers.size,
    totalSockets: userSockets.size
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`WebSocket server running on port ${PORT}`);
});
