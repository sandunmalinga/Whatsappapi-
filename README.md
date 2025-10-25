A Node.js-based WhatsApp messaging API using [Baileys](https://github.com/adiwajshing/Baileys).  
Send messages programmatically via multiple devices with support for API keys, session management, and user accounts.

1.Install dependencies
npm install

2. Restore Database via phpMyAdmin
In phpMyAdmin, select your database (create a new one if needed).
Click on the Import tab.
Click Choose File and select database/whatsapp_multi.sql.
Make sure the format is SQL.
Click Go — phpMyAdmin will import the tables and data.

3.Step 3: Configure Database Credentials
Replace the values with your own database credentials:

const db = mysql.createPool({
  host: "localhost",
  user: "root",
  password: "",
  database: "whatsapp_multi",
});


4.Open a terminal or command prompt in your project folder (where index.js is located).

Run the server:

node index.js


5. User Registration
localhost:3000/register.php

Username – your desired username
Password – secure password

Click Register.

Upon successful registration, you can now log in using login.php.

6. Navigate the Web Interface

After logging in, the application provides a side menu for easy navigation:

Dashboard – View API status and recent activity

Add Device – Register a new WhatsApp device

Devices – List all registered devices and manage them

Send Message – Send single messages through a selected device

API Documentation – View API endpoints and code examples

Logout – Sign out of your account

The side menu is always visible on the left-hand side, allowing quick access to all features of the WhatsApp Sender API.





   

