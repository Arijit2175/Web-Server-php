<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My PHP Web Server</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <h1>Welcome to My PHP Web Server</h1>
    <p>This is the home page. Use the forms below to test POST requests and file uploads.</p>

    <hr>

    <h2>Test Form Submission</h2>
    <form action="/submit" method="POST">
        <label>Your Name:</label>
        <input type="text" name="name" required>
        <button type="submit">Submit</button>
    </form>

    <hr>

    <h2>Safe File Upload</h2>
    <form action="/upload" method="POST" enctype="multipart/form-data">
        <label>Select file (JPG, PNG, GIF, PDF, Max 2MB):</label>
        <input type="file" name="file" required>
        <button type="submit">Upload</button>
    </form>

    <hr>

    <a href="/about">About Page</a>
</body>
</html>
