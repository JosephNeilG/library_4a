<h1 id="library-management-system">Library Management System with Token-Based Authentication</h1>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#library-management-system">About The Project</a>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installing">Installing</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a>
        <ul>
        <li><a href="#user-endpoints">User Endpoints</a></li>
        <li><a href="#author-endpoints">Author Endpoints</a></li>
        <li><a href="#book-endpoints">Book Endpoints</a></li>
        <li><a href="#book-author-association-endpoints">Book-Author Association Endpoints</a></li>
      </ul>
    </li>
    <li><a href="#token-management">Token Management</a></li>
    <li><a href="#troubleshooting--faq">Troubleshooting / FAQ</a></li>
    <li><a href="#project-information">Project Information</a></li>
  </ol>
</details>

## About the Project

The Library Management System provides a secure and efficient way to manage books, authors, users, and book-author relationships. It supports CRUD operations for users (register, authenticate, display, update, delete), books, authors, and book-author associations. Token-based authentication ensures secure access, with validation and usage tracking to restrict operations to authorized users. The book-author relationship table enhances flexibility, linking books to their respective authors. The system aims to facilitate easy and secure management of library data while maintaining a high level of security.

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

## Getting Started

### Prerequisites

- XAMPP
- SQLyog (optional, can use phpMyAdmin)
- Composer
- Node.js
- PHP (version 7.2 or higher)
- Slim Framework
- JWT PHP Library
- ThunderClient

### Installing

1. **Clone the Repository**

   ```bash
   git clone https://github.com/github_username/library_4a.git
   cd /path/to/xampp/htdocs/library_4a

   ```

2. **Install Dependencies**

   - Use Composer to install PHP dependencies:

   ```bash
   composer install

   ```

3. **Set Up Database**

   - Open SQLyog or phpMyAdmin and create a new database called `library`.
   - Run the following SQL queries to create the required tables:

   ```sql
   CREATE TABLE users (
       userid INT(9) NOT NULL AUTO_INCREMENT,
       username CHAR(255) NOT NULL,
       password TEXT NOT NULL,
       PRIMARY KEY (userid)
   );

   CREATE TABLE authors (
       authorid INT(9) NOT NULL AUTO_INCREMENT,
       name CHAR(255) NOT NULL,
       PRIMARY KEY (authorid)
   );

   CREATE TABLE books (
       bookid INT(9) NOT NULL AUTO_INCREMENT,
       title CHAR(255) NOT NULL,
       PRIMARY KEY (bookid)
   );

   CREATE TABLE books_authors (
       collectionid INT(9) NOT NULL AUTO_INCREMENT,
       bookid INT(9) NOT NULL,
       authorid INT(9) NOT NULL,
       PRIMARY KEY (collectionid)
   );

   CREATE TABLE used_tokens (
       token VARCHAR(512) PRIMARY KEY,
       used_at DATETIME NOT NULL
   );
   ```

4. **Configure Database Connection**

   - Edit the connection details in index.php as follows:

   ```php
   <?php
   $servername = "localhost";
   $username = "root";
   $password = "password";
   $dbname = "library";
   ?>
   ```

   Replace these values with your actual database settings to connect to the library database.

5. **Start XAMPP Server**

   - Ensure that both Apache and MySQL are running in the XAMPP control panel.

6. **Testing the Application**
   - You can now use API testing tools like Postman or Thunder Client to test the CRUD operations and authentication endpoints.

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

## Usage

<h3 id="user-endpoints">1. User Endpoints</h3>

**a. User Registration** - Registers a new user with a unique username and a hashed password.

- **Endpoint:** `/user/register`
- **Method:** `POST`
- **Sample Payload:**

  ```json
  {
    "username": "newUser",
    "password": "securePassword"
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:**

    ```json
    {
      "status": "fail",
      "data": {
        "title": "<Error Message Here>"
      }
    }
    ```

**b. User Authentication** - Authenticates a user and generates a JWT token for session management.

- **Endpoint:** `/user/authenticate`
- **Method:** `POST`
- **Sample Payload:**

  ```json
  {
    "username": "existingUser",
    "password": "securePassword"
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "token": "jwtTokenHere",
      "data": null
    }
    ```

  - **Failure:**

    ```json
    {
      "status": "fail",
      "data": {
        "title": "Authentication Failed"
      }
    }
    ```

**c. Display Users** - Retrieves a list of all users in the system; requires a valid token.

- **Endpoint:** `/user/display`
- **Method:** `GET`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": [
        {
          "userid": 1,
          "username": "username"
        }
      ]
    }
    ```

  - **Failure:** Token Already Used

    ```json
    {
      "status": "fail",
      "data": {
        "title": "Token has already been used"
      }
    }
    ```

  - **Failure:** Invalid or Expired Token

    ```json
    {
      "status": "fail",
      "data": {
        "title": "Invalid or expired token"
      }
    }
    ```

**d. Update User Information** - Updates the user's username and/or password; requires a valid token.

- **Endpoint:** `/user/update`
- **Method:** `PUT`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "username": "updatedUser",
    "password": "newSecurePassword"
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the new username is taken, if there’s nothing to update, or if the token is invalid, expired, or already used, an appropriate error message.

**e. Delete User** - Deletes the authenticated user’s account from the system; requires a valid token.

- **Endpoint:** `/user/delete`
- **Method:** `DELETE`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the user doesn’t exist, or if the token is invalid, expired, or already used, an appropriate error message.

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

<h3 id="author-endpoints">2. Author Endpoints</h3>

**a. Add Author** - Adds a new author to the database.

- **Endpoint:** `/author/add`
- **Method:** `POST`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "name": "Author Name"
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token is invalid, expired, already used, or if the name is empty or the author already exists, an appropriate error message will be returned.

**b. Display Author** - Displays a list of authors from the database.

- **Endpoint:** `/author/display`
- **Method:** `GET`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": [
        {
          "authorid": 1,
          "name": "Author Name"
        }
      ]
    }
    ```

  - **Failure:** If the token has already been used, is invalid, or has expired, an appropriate error message will be returned.

**c. Update Author** - Updates an author's information in the database.

- **Endpoint:** `/author/update`
- **Method:** `PUT`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "authorid": 1,
    "name": "Updated Author Name"
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token has already been used, is invalid, expired, or if the author ID is missing or not found, or if there are no fields to update, an appropriate error message will be returned.

**d. Delete Author** - Deletes an author from the database.

- **Endpoint:** `/author/delete`
- **Method:** `DELETE`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "authorid": 1
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token has already been used, is invalid, expired, or if the author ID is missing or not found, an appropriate error message will be returned.

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

<h3 id="book-endpoints">3. Book Endpoints</h3>

**a. Add Book** - Adds a new book to the database.

- **Endpoint:** `/book/add`
- **Method:** `POST`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "title": "Book Title"
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token is invalid, expired, already used, or if the title is empty or the book already exists, an appropriate error message will be returned.

**b. Display Books** - Displays a list of books from the database.

- **Endpoint:** `/book/display`
- **Method:** `GET`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": [
        {
          "bookid": 1,
          "title": "Book Title"
        }
      ]
    }
    ```

  - **Failure:** If the token has already been used, is invalid, or expired, an appropriate error message will be returned.

**c. Update Book** - Updates a book's information in the database.

- **Endpoint:** `/book/update`
- **Method:** `PUT`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "bookid": 1,
    "title": "Updated Book Title"
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token has already been used, is invalid, expired, or if the book ID is missing or not found, or if there are no fields to update, an appropriate error message will be returned.

**d. Delete Book** - Deletes a book from the database.

- **Endpoint:** `/book/delete`
- **Method:** `DELETE`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "bookid": 1
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token has already been used, is invalid, expired, or if the book ID is missing or not found, an appropriate error message will be returned.

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

<h3 id="book-author-association-endpoints">4. Book-Author Association Endpoints</h3>

**a. Add Book-Author** - Adds a new association between a book and an author.

- **Endpoint:** `/books_author/add`
- **Method:** `POST`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "bookid": 1,
    "authorid": 2
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token is already used, invalid/expired, or if required fields (book ID or author ID) are missing, the response will indicate the specific error.

**b. Display All Book-Author** - Displays all book-author associations in the database with their corresponding IDs.

- **Endpoint:** `/books_author/display`
- **Method:** `GET`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": [
        {
          "collectionid": 1,
          "bookid": 1,
          "authorid": 2
        }
      ]
    }
    ```

  - **Failure:** If the token is already used, invalid/expired, or any database issue occurs, the response will indicate the specific error.

**c. Display Book-Author with Names** - Displays book-author associations with the book and author names instead of IDs.

- **Endpoint:** `/books_author/display_with_names`
- **Method:** `GET`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": [
        {
          "collectionid": 1,
          "book_name": "Book Title 1",
          "author_name": "Author Name 1"
        }
      ]
    }
    ```

  - **Failure:** If the token is already used, invalid/expired, or any database issue occurs, the response will indicate the specific error.

**d. Update Book-Author** - Updates an existing book-author association by changing the book and/or author ID.

- **Endpoint:** `/books_author/update`
- **Method:** `PUT`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "collectionid": 1,
    "bookid": 2,
    "authorid": 3
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token is already used, invalid/expired, if the collection ID is missing or not found, or no fields are provided to update, the response will indicate the specific error.

**e. Delete Book-Author** - Deletes a specific book-author association.

- **Endpoint:** `/books_author/delete`
- **Method:** `DELETE`
- **Headers:** `Authorization: Bearer <insert generated jwtTokenHere from the users/authenticate>`
- **Sample Payload:**

  ```json
  {
    "collectionid": 1
  }
  ```

- **Expected Response:**

  - **Success:**

    ```json
    {
      "status": "success",
      "data": null
    }
    ```

  - **Failure:** If the token is already used, invalid/expired, if the collection ID is missing or no association exists for the given ID, the response will indicate the specific error.

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

## Token Management

**Check if Token is Used**  
The `isTokenUsed` function checks the `used_tokens` table to see if the token has been recorded as used.

```php
function isTokenUsed($token, $conn)
{
    $stmt = $conn->prepare("SELECT * FROM used_tokens WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}
```

**Validate Token**  
The `validateToken` function decodes and validates the token using the secret key, returning `false` if the token is invalid or expired.

```php
function validateToken($token, $key)
{
    try {
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (Exception $e) {
        return false;
    }
}
```

**Mark Token as Used**  
The `markTokenAsUsed` function inserts the token into the `used_tokens` table, marking it as used to prevent reuse.

```php
function markTokenAsUsed($conn, $token)
{
    try {
        $stmt = $conn->prepare("INSERT INTO used_tokens (token) VALUES (:token)");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
    } catch (PDOException $e) {
        throw new Exception("Error marking token as used: " . $e->getMessage());
    }
}
```

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

## Troubleshooting / FAQ

- **Q: How do I regenerate an expired token?**  
  **A:** To regenerate an expired token, you need to log in again by providing valid credentials. The new token will be issued upon successful authentication.

- **Q: Why am I getting a "Token is invalid" error?**  
  **A:** This error occurs if the token is either malformed or has expired. Ensure the token is correctly formatted and that it is not expired. If necessary, regenerate a new token.

- **Q: How do I check if my token has already been used?**  
  **A:** You can check if your token has been used by calling the API endpoint that checks the token's status, or by inspecting the `used_tokens` table in the database.

- **Q: What should I do if I get a "Token expired" error when calling the API?**  
  **A:** This error means the token has expired. Regenerate the token by logging in again, and ensure you are using the new token for your API requests.

- **Q: Can I use the same token multiple times?**  
  **A:** No, the token can only be used once. After it is used, it will be marked as "used" in the database, and any subsequent attempts to reuse it will result in a "Token already used" error.

- **Q: Where will I put the token if I'm using Thunder Client?**  
  **A:**
  - Open Thunder Client and create a new request.
  - Go to the "Headers" tab.
  - Add a new key called `Authorization`.
  - Set the value to `Bearer <your_token>`, where `<your_token>` is the JWT token you want to use.  
    For example:  
    `Authorization: Bearer your_token_here`

<p align="right">(<a href="#library-management-system">back to top</a>)</p>

## Project Information

This project is developed as part of a midterm school requirement for ITPC 115. It is aimed at demonstrating knowledge and skills in building secure API endpoints and token management.

<p align="right">(<a href="#library-management-system">back to top</a>)</p>
