<div align="center">

# 🗓️ Vacation Management System

### Employee vacation request and approval platform

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

</div>

---

## 📖 About the Project

Vacation Management System is a web application for creating, reviewing and managing employee vacation requests.

It supports administrators, managers and collaborators, with role-specific dashboards and approval workflows.

All company names, accounts and records included in the repository are fictional and exist exclusively for demonstration purposes.

---

## 📸 Screenshots

<p align="center">
  <img src="docs/screenshots/login.png" alt="Login screen" width="48%">
  <img src="docs/screenshots/dashboard.png" alt="Dashboard" width="48%">
</p>

> Screenshots will be added after the public version is fully prepared.

---

## ✨ Main Features

### 🛡️ Administrator

- Create and edit users
- Assign collaborators to managers
- Enable and disable accounts
- Review relationship history
- Access system auditing information

### 🧑‍💼 Manager

- View team vacation requests
- Approve or reject requests
- Review previous decisions
- Monitor collaborator availability

### 👤 Collaborator

- Submit vacation requests
- Automatically calculate working days
- Review request status
- View request history
- Receive in-application notifications

---

## 🛠️ Tech Stack

| Area | Technologies |
|---|---|
| Backend | PHP 8 |
| Database | MySQL |
| Database access | PDO, Prepared Statements |
| Frontend | HTML5, CSS3, JavaScript |
| Authentication | PHP sessions, BCrypt |
| Auditing | MySQL triggers |

---

## 📋 Requirements

- PHP 8
- Apache
- MySQL 5.7 or MySQL 8
- XAMPP, WAMP, MAMP or a comparable local environment

---

## ⚙️ Setup

### 1. Clone the repository

```bash
git clone https://github.com/dyzern/vacation-management-system.git
cd vacation-management-system
```

### 2. Create the database

```sql
SOURCE database/sistema_ferias.sql;
```

### 3. Load the demonstration data

```sql
SOURCE database/seed_demo.sql;
```

### 4. Configure the connection

Create the local configuration based on `.env.example`.

| Variable | Description | Local default |
|---|---|---|
| `DB_HOST` | MySQL server | `localhost` |
| `DB_NAME` | Database name | `sistema_ferias` |
| `DB_USER` | Database user | `root` |
| `DB_PASSWORD` | Database password | Empty |

Use a dedicated database account and a strong password outside a local development environment.

### 5. Run the application

Place the project in the Apache document root and open it in the browser.

Example using XAMPP:

```text
http://localhost/vacation-management-system
```

---

## 👥 Demo Accounts

The demonstration data contains fictional accounts.

All demo users use:

```text
Password: 123456
```

| Role | Username |
|---|---|
| Administrator | `admin` |
| Manager | `maria.responsavel` |
| Collaborator | `joao.colaborador` |

These credentials are intended exclusively for local demonstrations.

---

## 🔐 Security Notes

The project already includes:

- Password hashing using `password_hash()`
- Password verification using `password_verify()`
- Session regeneration after login
- PDO prepared statements
- Logical account deactivation
- Database-level audit triggers

Known security limitations:

- CSRF protection is not yet implemented.
- Output escaping should be applied consistently to all dynamic content.
- The local database configuration uses development defaults.

---

## ⚠️ Known Limitations

- No automated test suite
- No email notification integration
- Limited protection against CSRF
- Output escaping requires additional consistency
- The current structure does not use a full MVC framework

---

## 🚀 Future Improvements

- Add CSRF tokens to every state-changing form
- Apply consistent output escaping
- Add email notifications
- Introduce an MVC structure
- Add automated unit and integration tests
- Improve responsive layouts
- Add calendar views and team availability reports

---

## 👤 Author

**Ruben Gonçalves**

This repository is published for portfolio and educational purposes.