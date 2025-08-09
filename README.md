# PHP Multi-Client Web Server

A custom **multi-client, socket-based web server** written entirely in PHP.  
Supports static file serving, custom routes, file uploads, and downloads.

---

## 📌 Features
- **Socket-based server** using PHP's `socket_*` functions
- **Multi-client support** via non-blocking sockets
- **Custom routing** for GET & POST endpoints
- **Static file serving** with extension-based MIME detection
- **File uploads** with validation (max 5 MB, safe filenames, timestamped)
- **File downloads** with secure path checks
- **Request logging** with timestamps, IP, status codes
- **Security checks** for directory traversal and forbidden extensions

---

## 📂 Project Structure

root/
│
├── server.php # Main server script
├── public/ # Public static files 
      │
      ├── index.html
      ├──styles.css
├── views/ # PHP templates for dynamic pages
      │
      ├── home.php
      ├── about.php
      ├── submit.php
├── uploads/ # Uploaded files (auto-created)
└── logs/ # Server logs (auto-created)
      │
      ├── server.log

---

