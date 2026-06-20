# GitHub Repository Setup Guide

Use this guide to put the `student-routine-organizer` project on GitHub while still running it through XAMPP.

## 1. Correct Folder To Use

The Git repository should be created inside the project folder only:

```text
C:\xampp\htdocs\student-routine-organizer
```

Do not create a Git repository in:

```text
C:\xampp
```

Do not create a Git repository in:

```text
C:\xampp\htdocs
```

The correct `.git` folder should eventually be:

```text
C:\xampp\htdocs\student-routine-organizer\.git
```

## 2. Why The Project Can Stay Inside XAMPP

For PHP projects, the folder must be inside XAMPP's `htdocs` folder so Apache can run it.

That means this folder can do both jobs:

```text
C:\xampp\htdocs\student-routine-organizer
```

It is:

- The runnable website folder.
- The folder opened in VS Code.
- The folder tracked by Git.
- The folder pushed to GitHub.

When you edit files here, you can immediately test them in the browser:

```text
http://localhost/student-routine-organizer/
```

## 3. Install Git First

If Git is not installed, download it here:

```text
https://git-scm.com/download/win
```

After installation, open PowerShell or VS Code terminal and check:

```bash
git --version
```

If a version number appears, Git is ready.

## 4. Create A New Repository On GitHub

1. Go to:

```text
https://github.com
```

2. Login to your GitHub account.

3. Click the `+` button or `New repository`.

4. Repository name:

```text
student-routine-organizer
```

5. Choose `Private` if only your group should see it.

6. Do not tick these options if the local project already has files:

- Add a README file
- Add `.gitignore`
- Choose a license

7. Click `Create repository`.

8. Keep the GitHub page open because you need the repository URL.

Example HTTPS URL:

```text
https://github.com/YOUR_USERNAME/student-routine-organizer.git
```

Replace `YOUR_USERNAME` with your GitHub username.

## 5. Open The Project In VS Code

Open VS Code.

Then open this folder:

```text
C:\xampp\htdocs\student-routine-organizer
```

In VS Code:

1. Click `File`.
2. Click `Open Folder`.
3. Select `C:\xampp\htdocs\student-routine-organizer`.
4. Open the terminal in VS Code.

The terminal path should be:

```text
C:\xampp\htdocs\student-routine-organizer
```

## 6. Add A `.gitignore` File

Inside:

```text
C:\xampp\htdocs\student-routine-organizer
```

create a file named:

```text
.gitignore
```

Recommended content:

```gitignore
.DS_Store
Thumbs.db
*.log
tmp/
.env
```

For this assignment, it is okay to commit:

```text
database/student_routine_organizer.sql
```

Your teammates need that SQL file to import the database.

## 7. Initialize Git Locally

In VS Code terminal, run:

```bash
cd C:\xampp\htdocs\student-routine-organizer
git init
git status
```

Expected result:

- Git initializes the repository.
- Files appear as untracked.

## 8. First Commit

Run:

```bash
git add .
git commit -m "Initial project setup"
```

If Git asks for your name/email, run:

```bash
git config --global user.name "Your Name"
git config --global user.email "your-email@example.com"
```

Then run the commit command again.

## 9. Connect Local Repo To GitHub

Run:

```bash
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/student-routine-organizer.git
```

Replace this part:

```text
YOUR_USERNAME
```

with your GitHub username.

## 10. Push To GitHub

Run:

```bash
git push -u origin main
```

If GitHub asks you to login, follow the browser login or authentication prompt.

After pushing, refresh the GitHub repository page. You should see your project files.

## 11. Daily Workflow After GitHub Is Set Up

Every time you finish a meaningful change:

```bash
cd C:\xampp\htdocs\student-routine-organizer
git status
git add .
git commit -m "Describe what changed"
git push
```

Example:

```bash
git add .
git commit -m "Add exercise tracker create page"
git push
```

## 12. How Teammates Clone The Project

Each teammate should install XAMPP first.

Then they should open PowerShell or VS Code terminal and run:

```bash
cd C:\xampp\htdocs
git clone https://github.com/YOUR_USERNAME/student-routine-organizer.git
```

Then they open:

```text
http://localhost/student-routine-organizer/
```

They must also import the database:

```text
C:\xampp\htdocs\student-routine-organizer\database\student_routine_organizer.sql
```

into phpMyAdmin.

## 13. How To Get Latest Team Changes

Before starting work each day:

```bash
cd C:\xampp\htdocs\student-routine-organizer
git pull
```

After pulling, test:

```text
http://localhost/student-routine-organizer/
```

## 14. Important Team Rules

1. Work inside `C:\xampp\htdocs\student-routine-organizer`.
2. Do not edit random copies outside the repo.
3. Pull before starting work.
4. Commit and push after finishing a feature.
5. Do not push the whole `C:\xampp` folder.
6. Do not delete the `database/student_routine_organizer.sql` file.
7. Do not create a second login system.
8. Do not rename shared tables without telling the group.

## 15. What Should Be On GitHub

GitHub should include:

```text
admin/
assets/
config/
database/
docs/
includes/
modules/
dashboard.php
index.php
login.php
logout.php
register.php
README.txt
.gitignore
```

GitHub should not include:

```text
C:\xampp
Apache logs
MySQL data folder
Temporary files
Personal passwords
```

