# Northstar Student Enrollment

A responsive PHP and MySQL student directory with create, view, update, and delete operations.

## Run with XAMPP

1. Place the project in `D:\xampp1\htdocs\Quimpan_LabExam2`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, select **Import**, choose `database.sql`, and run the import.
4. Open `http://localhost/Quimpan_LabExam2/`.

The default connection in `config.php` uses MySQL at `127.0.0.1:3306`, the `student_enrollment` database, and XAMPP's default `root` user with no password. This XAMPP installation uses port `3306`; update the host, port, or credentials in `config.php` if your local MySQL settings differ.

The SQL import creates the database and table and adds six fictional sample students using reserved `example.com` email addresses. Students can be added, searched, filtered by enrollment status, edited, and deleted from the directory.

## Student fields

Each record includes the student's name, email, phone, gender, date of birth, course, year level, enrollment date, enrollment status, and an optional address. Student IDs are generated automatically.
