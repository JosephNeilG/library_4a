# Library Management System with Token-Based Authentication

The Library Management System provides a secure and efficient way to manage books, authors, users, and book-author relationships. It supports CRUD operations for users (register, authenticate, display, update, delete), books, authors, and book-author associations. Token-based authentication ensures secure access, with validation and usage tracking to restrict operations to authorized users. The book-author relationship table enhances flexibility, linking books to their respective authors. The system aims to facilitate easy and secure management of library data while maintaining a high level of security.

## Getting Started

### Prerequisites

- XAMPP
- SQLyog (optional, can use phpMyAdmin)
- Composer
- Node.js
- PHP (version 7.2 or higher)
- Slim Framework
- JWT PHP Library

### Installing

1. **Clone the Repository**

   ```bash
   git clone https://github.com/github_username/library_4a.git
   cd /path/to/xampp/htdocs/library_4a

   ```

2. **Install Dependencies**  
   Use Composer to install PHP dependencies:

   ```bash
   composer install

   ```

3. **Set Up Database**

   - Open SQLyog or phpMyAdmin and create a new database called `library`.
   - Run the following SQL queries to create the required tables:

   ```sql
   -- Create `users` table
   CREATE TABLE users (
       userid INT(9) NOT NULL AUTO_INCREMENT,
       username CHAR(255) NOT NULL,
       password TEXT NOT NULL,
       PRIMARY KEY (userid)
   );

   -- Create `authors` table
   CREATE TABLE authors (
       authorid INT(9) NOT NULL AUTO_INCREMENT,
       name CHAR(255) NOT NULL,
       PRIMARY KEY (authorid)
   );

   -- Create `books` table
   CREATE TABLE books (
       bookid INT(9) NOT NULL AUTO_INCREMENT,
       title CHAR(255) NOT NULL,
       PRIMARY KEY (bookid)
   );

   -- Create `books_authors` table
   CREATE TABLE books_authors (
       collectionid INT(9) NOT NULL AUTO_INCREMENT,
       bookid INT(9) NOT NULL,
       authorid INT(9) NOT NULL,
       PRIMARY KEY (collectionid)
   );

   -- Create `used_tokens` table (provided)
   CREATE TABLE used_tokens (
       token VARCHAR(512) PRIMARY KEY,
       used_at DATETIME NOT NULL
   );
   ```

End with an example of getting some data out of the system or using it for a little demo

## Running the tests

Explain how to run the automated tests for this system

### Break down into end to end tests

Explain what these tests test and why

```
Give an example
```

### And coding style tests

Explain what these tests test and why

```
Give an example
```

## Deployment

Add additional notes about how to deploy this on a live system

## Built With

- [Dropwizard](http://www.dropwizard.io/1.0.2/docs/) - The web framework used
- [Maven](https://maven.apache.org/) - Dependency Management
- [ROME](https://rometools.github.io/rome/) - Used to generate RSS Feeds

## Contributing

Please read [CONTRIBUTING.md](https://gist.github.com/PurpleBooth/b24679402957c63ec426) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/your/project/tags).

## Authors

- **Billie Thompson** - _Initial work_ - [PurpleBooth](https://github.com/PurpleBooth)

See also the list of [contributors](https://github.com/your/project/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

- Hat tip to anyone whose code was used
- Inspiration
- etc
