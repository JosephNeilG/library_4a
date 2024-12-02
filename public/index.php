<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require '../src/vendor/autoload.php';

$app = new \Slim\App;

// Secret key for JWT
$key = 'server_hack';

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "library";

// Token rotation function for every endpoint
function generateToken($userid)
{
    global $key;

    // Token generation
    $iat = time(); // Issued at time
    $exp = $iat + 7200; // Expiration time (2 hours)

    // Payload data for JWT
    $payload = [
        'iss' => 'http://library.org', // Issuer
        'aud' => 'http://library.com', // Audience
        'iat' => $iat,                 // Issued at time
        'exp' => $exp,                 // Expiration time
        'data' => [
            'userid' => $userid        // User ID data
        ]
    ];

    // Generate and return the token
    return JWT::encode($payload, $key, 'HS256');
}


// Function to check if the token has been used
function isTokenUsed($token, $conn)
{
    $stmt = $conn->prepare("SELECT * FROM used_tokens WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    return $stmt->rowCount() > 0;  // Returns true if token is found (i.e., used)
}

// Function to validate the token
function validateToken($token, $key)
{
    try {
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (Exception $e) {
        return false;  // Token is invalid or expired
    }
}

function createDatabaseConnection($servername, $username, $password, $dbname)
{
    try {
        // Create a new PDO connection
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;  // Return the PDO connection
    } catch (PDOException $e) {
        // Handle connection errors
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

function markTokenAsUsed($conn, $token)
{
    try {
        $stmt = $conn->prepare("INSERT INTO used_tokens (token) VALUES (:token)");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
    } catch (PDOException $e) {
        // Handle potential errors (optional)
        throw new Exception("Error marking token as used: " . $e->getMessage());
    }
}

// user register 
$app->post('/user/register', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $data = json_decode($request->getBody());
    $usr = $data->username;
    $pass = $data->password;

    try {
        // Check if password is empty
        if (empty($pass)) {
            $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => "Password cannot be empty"))));
            return $response->withStatus(400); // Bad request status
        }

        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the username already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $usr);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Username already exists, return error
            $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => "Username already taken"))));
        } else {
            // Username does not exist, proceed with the registration
            $sql = "INSERT INTO users (username, password) VALUES (:username, :password)";
            $stmt = $conn->prepare($sql);
            $hashedPassword = hash('SHA256', $pass); // Hashing password using SHA-256

            $stmt->bindParam(':username', $usr);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->execute();

            $response->getBody()->write(json_encode(array("status" => "success", "data" => null)));
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
    }

    return $response;
});

$app->post('/user/authenticate', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $data = json_decode($request->getBody());
    $usr = $data->username;
    $pass = $data->password;

    try {
        // Create a new PDO connection
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepare and execute the SQL query securely
        $stmt = $conn->prepare("SELECT userid FROM users WHERE username = :username AND password = :password");
        $stmt->bindParam(':username', $usr);
        $hashedPassword = hash('SHA256', $pass); // Assign the hash to a variable
        $stmt->bindParam(':password', $hashedPassword); // Pass the variable to bindParam
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $data = $stmt->fetchAll();


        if (count($data) === 1) {
            // Generate a token using the helper function
            $jwt = generateToken($data[0]['userid']);

            // Return success response with the token
            $response->getBody()->write(json_encode([
                "status" => "success",
                "token" => $jwt,
                "data" => null
            ]));
        } else {
            // Authentication failed response
            $response->getBody()->write(json_encode([
                "status" => "fail",
                "data" => ["title" => "Authentication Failed"]
            ]));
        }
    } catch (PDOException $e) {
        // Handle database connection errors
        $response->getBody()->write(json_encode([
            "status" => "fail",
            "data" => ["title" => $e->getMessage()]
        ]));
    }
    return $response;
});


$app->get('/user/display', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);  // Remove "Bearer " from token if present

    try {
        // Create a new PDO connection
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode([
                "status" => "fail",
                "data" => ["title" => "Token has already been used"]
            ]));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode([
                "status" => "fail",
                "data" => ["title" => "Invalid or expired token"]
            ]));
            return $response->withStatus(401);  // Unauthorized
        }

        // Fetch the users from the database
        $stmt = $conn->prepare("SELECT userid, username FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token using the function
        $newToken = generateToken($decoded->data->userid);

        // Return the users and the new token in the response
        $response->getBody()->write(json_encode([
            "status" => "success",
            "token" => $newToken,
            "data" => $users

        ]));
        return $response->withStatus(200);  // Success
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode([
            "status" => "fail",
            "data" => ["title" => $e->getMessage()]
        ]));
        return $response->withStatus(500);  // Internal Server Error
    }
});

// Update user info (username and/or password) with token validation and token cannot be reused
$app->put('/user/update', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());

    $newUsername = $data->username ?? null;  // Optional field
    $newPassword = $data->password ?? null;  // Optional field

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            // Token has already been used, return an error
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            // Token validation failed (invalid or expired token)
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract the user ID from the token's payload
        $userId = $decoded->data->userid;

        // Ensure at least one field is being updated (username or password)
        if (empty($newUsername) && empty($newPassword)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "No fields to update")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // If updating username, check for uniqueness
        if ($newUsername) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND userid != :userid");
            $stmt->bindParam(':username', $newUsername);
            $stmt->bindParam(':userid', $userId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // Username already exists
                $response->getBody()->write(json_encode(array(
                    "status" => "fail",
                    "data" => array("title" => "Username already taken")
                )));
                return $response->withStatus(400);  // Bad Request
            }
        }

        // Prepare the update statement
        $updateFields = [];
        $updateValues = [];

        if ($newUsername) {
            $updateFields[] = "username = :username";
            $updateValues[':username'] = $newUsername;
        }

        if ($newPassword) {
            // Hash the new password before storing it
            $hashedPassword = hash('SHA256', $newPassword);
            $updateFields[] = "password = :password";
            $updateValues[':password'] = $hashedPassword;
        }

        // Build and execute the update query
        $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE userid = :userid";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userid', $userId);
        foreach ($updateValues as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value);
        }
        $stmt->execute();

        // Mark the old token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token with updated user data
        $newToken = generateToken($userId);

        // Return the success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});


// Delete user by userid
$app->delete('/user/delete', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            // Token has already been used, return an error
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            // Token validation failed (invalid or expired token)
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract the user ID from the token's payload
        $userId = $decoded->data->userid;

        // Delete the user from the database
        $stmt = $conn->prepare("DELETE FROM users WHERE userid = :userid");
        $stmt->bindParam(':userid', $userId);
        $stmt->execute();

        // Check if any rows were affected
        if ($stmt->rowCount() === 0) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "User not found")
            )));
            return $response->withStatus(404);  // Not Found
        }

        // 6. Mark the token as used by inserting it into the `used_tokens` table
        $stmt = $conn->prepare("INSERT INTO used_tokens (token) VALUES (:token)");
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        // 7. Return success response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "data" => null
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// Add author
$app->post('/author/add', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $name = $data->name ?? null;

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            // Token has already been used, return an error
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            // Token validation failed (invalid or expired token)
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token
        $userId = $decoded->data->userid;

        // Ensure name is provided
        if (empty($name)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Name cannot be empty")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Check if the author name already exists
        $stmt = $conn->prepare("SELECT * FROM authors WHERE name = :name");
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Author already exists, return error
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Author already exists")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Insert new author into the database
        $sql = "INSERT INTO authors (name) VALUES (:name)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->execute();

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token with updated user information (if necessary)
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// Display author
$app->get('/author/display', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            // Token has already been used, return an error
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            // Token validation failed (invalid or expired token)
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Fetch the authors from the database
        $stmt = $conn->prepare("SELECT authorid, name FROM authors");
        $stmt->execute();
        $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user
        $newToken = generateToken($userId);

        // Return the authors as a response along with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => $authors
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});


// Update author info
$app->put('/author/update', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $authorId = $data->authorid ?? null;  // Required field
    $newName = $data->name ?? null;  // Optional field

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            // Token has already been used, return an error
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            // Token validation failed (invalid or expired token)
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Ensure the author ID is provided
        if (empty($authorId)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Author ID is required")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Prepare the update statement
        if ($newName) {
            // Check if the author ID exists
            $stmt = $conn->prepare("SELECT * FROM authors WHERE authorid = :authorid");
            $stmt->bindParam(':authorid', $authorId);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                // Author ID does not exist
                $response->getBody()->write(json_encode(array(
                    "status" => "fail",
                    "data" => array("title" => "Author ID not found")
                )));
                return $response->withStatus(404);  // Not Found
            }

            // Update the author's name
            $stmt = $conn->prepare("UPDATE authors SET name = :name WHERE authorid = :authorid");
            $stmt->bindParam(':name', $newName);
            $stmt->bindParam(':authorid', $authorId);
            $stmt->execute();
        } else {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "No fields to update")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});


// Delete author
$app->delete('/author/delete', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $authorId = $data->authorid ?? null;  // Required field

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            // Token has already been used, return an error
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            // Token validation failed (invalid or expired token)
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Ensure the author ID is provided
        if (empty($authorId)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Author ID is required")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Check if the author ID exists
        $stmt = $conn->prepare("SELECT * FROM authors WHERE authorid = :authorid");
        $stmt->bindParam(':authorid', $authorId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            // Author ID does not exist
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Author ID not found")
            )));
            return $response->withStatus(404);  // Not Found
        }

        // Proceed to delete the author
        $stmt = $conn->prepare("DELETE FROM authors WHERE authorid = :authorid");
        $stmt->bindParam(':authorid', $authorId);
        $stmt->execute();

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});


// Add book
$app->post('/book/add', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $title = $data->title ?? null;

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Ensure title is provided
        if (empty($title)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Title cannot be empty")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Check if the book title already exists
        $stmt = $conn->prepare("SELECT * FROM books WHERE title = :title");
        $stmt->bindParam(':title', $title);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Book already exists")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Insert new book into the database
        $sql = "INSERT INTO books (title) VALUES (:title)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->execute();

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// Display books
$app->get('/book/display', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Fetch the books from the database
        $stmt = $conn->prepare("SELECT bookid, title FROM books");
        $stmt->execute();
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user 
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => $books
        )));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// Update book info
$app->put('/book/update', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $bookId = $data->bookid ?? null;  // Required field
    $newTitle = $data->title ?? null;  // Optional field

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Ensure the book ID is provided
        if (empty($bookId)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Book ID is required")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // 4. Prepare the update statement
        if ($newTitle) {
            // Check if the book ID exists
            $stmt = $conn->prepare("SELECT * FROM books WHERE bookid = :bookid");
            $stmt->bindParam(':bookid', $bookId);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                // Book ID does not exist
                $response->getBody()->write(json_encode(array(
                    "status" => "fail",
                    "data" => array("title" => "Book ID not found")
                )));
                return $response->withStatus(404);  // Not Found
            }

            // Update the book's title
            $stmt = $conn->prepare("UPDATE books SET title = :title WHERE bookid = :bookid");
            $stmt->bindParam(':title', $newTitle);
            $stmt->bindParam(':bookid', $bookId);
            $stmt->execute();
        } else {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "No fields to update")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user 
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// Delete a book
$app->delete('/book/delete', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $bookId = $data->bookid ?? null;  // Required field

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Ensure the book ID is provided
        if (empty($bookId)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Book ID is required")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Check if the book ID exists
        $stmt = $conn->prepare("SELECT * FROM books WHERE bookid = :bookid");
        $stmt->bindParam(':bookid', $bookId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            // Book ID does not exist
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Book ID not found")
            )));
            return $response->withStatus(404);  // Not Found
        }

        // Delete the book
        $stmt = $conn->prepare("DELETE FROM books WHERE bookid = :bookid");
        $stmt->bindParam(':bookid', $bookId);
        $stmt->execute();

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user 
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

$app->post('/books_author/add', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $bookId = $data->bookid ?? null;
    $authorId = $data->authorid ?? null;

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);
        }

        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        if (empty($bookId) || empty($authorId)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Book ID and Author ID are required")
            )));
            return $response->withStatus(400);
        }

        // Check if the book ID exists
        $stmt = $conn->prepare("SELECT * FROM books WHERE bookid = :bookid");
        $stmt->bindParam(':bookid', $bookId);
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Book ID not found")
            )));
            return $response->withStatus(404);  // Not Found
        }

        // Check if the author ID exists
        $stmt = $conn->prepare("SELECT * FROM authors WHERE authorid = :authorid");
        $stmt->bindParam(':authorid', $authorId);
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Author ID not found")
            )));
            return $response->withStatus(404);  // Not Found
        }

        // Check if the association already exists
        $stmt = $conn->prepare("SELECT * FROM books_authors WHERE bookid = :bookid AND authorid = :authorid");
        $stmt->bindParam(':bookid', $bookId);
        $stmt->bindParam(':authorid', $authorId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "This author is already associated with the book")
            )));
            return $response->withStatus(400);
        }

        // Insert new association
        $stmt = $conn->prepare("INSERT INTO books_authors (bookid, authorid) VALUES (:bookid, :authorid)");
        $stmt->bindParam(':bookid', $bookId);
        $stmt->bindParam(':authorid', $authorId);
        $stmt->execute();

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});


// display books_authors
$app->get('/books_author/display', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Fetch the data from book_authors table
        $stmt = $conn->prepare("SELECT collectionid, bookid, authorid FROM books_authors");
        $stmt->execute();
        $bookAuthors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user 
        $newToken = generateToken($userId);

        // Return the results as a response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => $bookAuthors
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// display books_authors with labels instead of its id
$app->get('/books_author/display_with_names', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Join book_authors with books and authors tables to fetch names
        $stmt = $conn->prepare("
            SELECT 
                ba.collectionid, 
                b.title AS book_name, 
                a.name AS author_name 
            FROM books_authors ba
            JOIN books b ON ba.bookid = b.bookid
            JOIN authors a ON ba.authorid = a.authorid
        ");
        $stmt->execute();
        $bookAuthorsWithNames = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user 
        $newToken = generateToken($userId);

        // Return the results as a response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => $bookAuthorsWithNames
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// update books_authors table
$app->put('/books_author/update', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $collectionId = $data->collectionid ?? null;  // Required field
    $newBookId = $data->bookid ?? null;            // Optional field
    $newAuthorId = $data->authorid ?? null;        // Optional field

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Ensure the collection ID is provided
        if (empty($collectionId)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Collection ID is required")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Check if the collection ID exists
        $stmt = $conn->prepare("SELECT * FROM books_authors WHERE collectionid = :collectionid");
        $stmt->bindParam(':collectionid', $collectionId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            // Collection ID does not exist
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Collection ID not found")
            )));
            return $response->withStatus(404);  // Not Found
        }

        // Update bookid and/or authorid if provided
        $updateFields = [];
        $params = ['collectionid' => $collectionId];

        if (!empty($newBookId)) {
            $updateFields[] = "bookid = :bookid";
            $params['bookid'] = $newBookId;
        }

        if (!empty($newAuthorId)) {
            $updateFields[] = "authorid = :authorid";
            $params['authorid'] = $newAuthorId;
        }

        if (empty($updateFields)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "No fields to update")
            )));
            return $response->withStatus(400);  // Bad Request
        }

        // Prepare the update statement
        $sql = "UPDATE books_authors SET " . implode(", ", $updateFields) . " WHERE collectionid = :collectionid";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user 
        $newToken = generateToken($userId);

        // Return success response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});

// delete books_authors
$app->delete('/books_author/delete', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);
    $data = json_decode($request->getBody());
    $collectionId = $data->collectionid ?? null;  // Get collectionid from request body

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Check if collection ID is provided
        if (empty($collectionId)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Collection ID is required")
            )));
            return $response->withStatus(400);
        }

        // Check if the association exists
        $stmt = $conn->prepare("SELECT * FROM books_authors WHERE collectionid = :collectionid");
        $stmt->bindParam(':collectionid', $collectionId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "No association found with the given collection ID")
            )));
            return $response->withStatus(404);
        }

        // Delete the association
        $stmt = $conn->prepare("DELETE FROM books_authors WHERE collectionid = :collectionid");
        $stmt->bindParam(':collectionid', $collectionId);
        $stmt->execute();

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user 
        $newToken = generateToken($userId);

        // Return success response with the new token
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => null
        )));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);
    }

    return $response;
});

// Combined endpoint to fetch books, authors, and book-author relationships
$app->get('/dashboard/data', function (Request $request, Response $response, array $args) use ($servername, $username, $password, $dbname, $key) {
    $token = $request->getHeader('Authorization')[0] ?? '';
    $token = str_replace('Bearer ', '', $token);

    try {
        // Create a new PDO connection using the function
        $conn = createDatabaseConnection($servername, $username, $password, $dbname);

        // Check if the token has already been used
        if (isTokenUsed($token, $conn)) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Token has already been used")
            )));
            return $response->withStatus(403);  // Forbidden
        }

        // Validate the token
        $decoded = validateToken($token, $key);
        if (!$decoded) {
            $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => "Invalid or expired token")
            )));
            return $response->withStatus(401);  // Unauthorized
        }

        // Extract user ID from token for rotation
        $userId = $decoded->data->userid;

        // Fetch data from the authors table
        $stmt = $conn->prepare("SELECT authorid, name FROM authors");
        $stmt->execute();
        $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch data from the books table
        $stmt = $conn->prepare("SELECT bookid, title FROM books");
        $stmt->execute();
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch data from the books_authors table (joining books and authors)
        $stmt = $conn->prepare("
            SELECT 
                ba.collectionid, 
                b.title AS book_name, 
                a.name AS author_name 
            FROM books_authors ba
            JOIN books b ON ba.bookid = b.bookid
            JOIN authors a ON ba.authorid = a.authorid
        ");
        $stmt->execute();
        $bookAuthorsWithNames = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark the token as used
        markTokenAsUsed($conn, $token);

        // Generate a new token for the user
        $newToken = generateToken($userId);

        // Return all the fetched data as a single response
        $response->getBody()->write(json_encode(array(
            "status" => "success",
            "token" => $newToken,
            "data" => array(
                'authors' => $authors,
                'books' => $books,
                'bookAuthors' => $bookAuthorsWithNames
            )
        )));
    } catch (PDOException $e) {
        // Handle DB errors
        $response->getBody()->write(json_encode(array(
            "status" => "fail",
            "data" => array("title" => $e->getMessage())
        )));
        return $response->withStatus(500);  // Internal Server Error
    }

    return $response;
});


$app->run();
