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

## 🚀 Workflow

### Requirements

- PHP **7.4+** with `sockets` extension enabled  
- CLI access to run the server
- If not present, enable it in **php.ini**.
Check if sockets are enabled:
```
php -m | grep sockets
```

### Run the Server

```
php server.php
```

By default it starts at:

```
http://127.0.0.1:8080
```

### Routes

| Method | Path                  | Description            |
| ------ | --------------------- | ---------------------- |
| GET    | `/`                   | Home page              |
| GET    | `/about`              | About page             |
| POST   | `/submit`             | Simple form submission |
| POST   | `/upload`             | File upload endpoint   |
| GET    | `/uploads`            | List of uploaded files |
| GET    | `/uploads/{filename}` | Download a file        |

---

## 📦 Uploads

- Max file size: **5 MB**
- Auto-renames with timestamp to avoid overwrites
- Cleans filenames from special characters

---

## 🛡 Security

- Only serves allowed file extensions (html, css, js, png, jpg, jpeg, gif, txt)
- Prevents directory traversal attacks
- Upload/download paths restricted to project folders

---

## 📜 Logging

- Every request is logged to logs/server.log:

```
[YYYY-MM-DD HH:MM:SS] <client_ip> "<METHOD> <PATH>" <STATUS_CODE>
```

---

## 🧠 Notes

- This is not production-grade — it’s a learning project.
- Designed to demonstrate socket programming and web server fundamentals in PHP.
- For production, use Apache/Nginx with PHP-FPM.

---

