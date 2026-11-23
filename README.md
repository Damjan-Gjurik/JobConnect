# 💼 Job Connect

Job Connect is a web application where companies can hire talent and candidates that can apply for compatible jobs.

---

## 📌 Main functions
- User registration and login for companies and candidates
- Applying for jobs
- Hiring talent
- Profile editing
- Password reset

---

## 🛠 Used technologies
- HTML5
- CSS3
- JavaScript
- PHP
- MySQL
- XAMPP

---

## 📷 Screenshots

### Home Page
![Home Page](Screenshots/Home-page.png)

---

### Categories Section
![Home Page](Screenshots/categories-section.png)

---

### Job Section
![Job Section](Screenshots/job-section.png)

---

### Job Details
![Job Details](Screenshots/job-details.png)

---

### My Profile
![My Profile](Screenshots/myProfile.png)

---

### Dashboard
![Dashboard](Screenshots/dashboard.png)

---

### Company Home Page
![Company Home Page](Screenshots/company-home-page.png)

---

### Company Dashboard
![Company Dashboard](Screenshots/company-dashboard.png)

---

### My Company
![My Company](Screenshots/myCompany.png

---

### Application section
![Application section](Screenshots/application-section.png)

---

### Application details
![Application details](Screenshots/application-details.png)

---

### Profile Candidate
![Profile Candidate](Screenshots/profile-candidate.png)

---

### Profile Company
![Profile Company](Screenshots/profile-company.png)

---

## ⚙️ Installation
1. Copy the repository
2. Paste it in the folder `xampp/htdocs`
3. Start Apache and mySQL
4. Upload the database `jobconnect.sql` in phpMyAdmin
5. Open: http://localhost/JobConnect/index.php

---

## 📂 Project Structure
JobConnect
│
├── README.md
├── Database
│   └── jobconnect.sql
├── Screenshots
│
├── Source
    ├── config
    │   └── database.php
    ├── php
    │   └── application-details.php
    │   ├── create-job.php
    │   ├── dashboard.php
    │   ├── job-details.php
    │   ├── login.php
    │   ├── logout.php
    │   ├── myCompany.php
    │   ├── myProfile.php
    │   ├── navbar.php
    │   ├── profile.php
    │   ├── register.php
    │   └── test.php
    │    
    ├── script
    │   └── script.js
    ├── style
    │   ├── pages.css
    │   ├── profile.css
    │   └── style.css
    ├── uploads
    └── index.php
