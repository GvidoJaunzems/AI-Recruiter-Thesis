<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Get the JWT secret key from environment variables or a default.
 * IMPORTANT: For production, always set a strong, unique JWT_SECRET_KEY environment variable.
 */
function get_jwt_secret_key() {
    return getenv('JWT_SECRET_KEY') ?: 'your-default-very-strong-secret-key-replace-this!'; // Replace with a strong default or ensure ENV var is set
}

/**
 * Get the JWT algorithm.
 */
function get_jwt_algorithm() {
    return 'HS256'; // Using HMAC SHA-256
}

/**
 * Generate a JWT token for a user.
 *
 * @param int $userId The user's ID.
 * @param string $username The user's username.
 * @param string $role The user's role (e.g., 'user', 'admin').
 * @param int $expirationTime Duration in seconds (e.g., 3600 for 1 hour).
 * @return string The generated JWT.
 */
function generate_jwt_token($userId, $username, $role, $expirationTime = 3600) {
    $secretKey = get_jwt_secret_key();
    $issuer = "http://yourdomain.com"; // Change to your domain
    $audience = "http://yourdomain.com"; // Change to your domain
    $issuedAt = time();
    $notBefore = $issuedAt; // Token is valid immediately
    $expire = $issuedAt + $expirationTime;

    $payload = [
        'iss' => $issuer,
        'aud' => $audience,
        'iat' => $issuedAt,
        'nbf' => $notBefore,
        'exp' => $expire,
        'data' => [ // Custom claims
            'userId' => $userId,
            'username' => $username,
            'role' => $role
        ]
    ];

    return JWT::encode($payload, $secretKey, get_jwt_algorithm());
}

/**
 * Validate a JWT token and return the decoded payload.
 *
 * @param string $token The JWT token from the Authorization header.
 * @return object|null The decoded payload object on success, null on failure.
 */
function validate_jwt_token($token) {
    if (!$token) {
        return null;
    }

    // Extract token from "Bearer <token>"
    if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        $token = $matches[1];
    }

    $secretKey = get_jwt_secret_key();

    try {
        $decoded = JWT::decode($token, new Key($secretKey, get_jwt_algorithm()));
        return $decoded;
    } catch (\Firebase\JWT\ExpiredException $e) {
        error_log('JWT Error: Token has expired - ' . $e->getMessage());
        return null; // Token expired
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        error_log('JWT Error: Signature verification failed - ' . $e->getMessage());
        return null; // Invalid signature
    } catch (Exception $e) {
        error_log('JWT Error: ' . $e->getMessage());
        return null; // Other validation error
    }
}

/**
 * Get the user ID from a validated JWT payload.
 *
 * @param object $decodedPayload The decoded payload from validate_jwt_token.
 * @return int|null The user ID if present, otherwise null.
 */
function get_user_id_from_token($decodedPayload) {
    if (isset($decodedPayload->data) && isset($decodedPayload->data->userId)) {
        return (int) $decodedPayload->data->userId;
    }
    return null;
}

/**
 * Get the user role from a validated JWT payload.
 *
 * @param object $decodedPayload The decoded payload from validate_jwt_token.
 * @return string|null The user role if present, otherwise null.
 */
function get_user_role_from_token($decodedPayload) {
    if (isset($decodedPayload->data) && isset($decodedPayload->data->role)) {
        return (string) $decodedPayload->data->role;
    }
    return null;
}

?> 