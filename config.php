<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '132456'; // Change as needed for your XAMPP setup
$database = 'restaurant_reservations';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    // If database doesn't exist, try to create it
    $conn = new mysqli($host, $username, $password);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $database";
    if ($conn->query($sql) === TRUE) {
        $conn->select_db($database);
        createTables($conn);
    }
} else {
    // Check if tables exist
    $result = $conn->query("SHOW TABLES LIKE 'reservations'");
    if ($result->num_rows == 0) {
        createTables($conn);
    }
}

function createTables($conn) {
    // Create users table first (needed for foreign key)
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) !== TRUE) {
        die("Error creating users table: " . $conn->error);
    }

    // Create reservations table
    $sql = "CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        reservation_date DATE NOT NULL,
        reservation_time TIME NOT NULL,
        guests INT NOT NULL,
        table_type VARCHAR(50) NOT NULL,
        special_requests TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    if ($conn->query($sql) !== TRUE) {
        die("Error creating reservations table: " . $conn->error);
    }

    // Create menu_items table
    $sql = "CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        category VARCHAR(50) NOT NULL,
        image_url VARCHAR(255),
        is_available TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) !== TRUE) {
        die("Error creating menu_items table: " . $conn->error);
    }

    // Create preorder_items table
    $sql = "CREATE TABLE IF NOT EXISTS preorder_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_id INT NOT NULL,
        menu_item_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price DECIMAL(10,2) NOT NULL,
        special_instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($sql) !== TRUE) {
        die("Error creating preorder_items table: " . $conn->error);
    }

    // Insert sample menu items if table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM menu_items");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $menu_items = [
            // Appetizers
            ["Truffle Arancini", "Crispy risotto balls infused with black truffle, served with garlic aioli", 14.00, "Appetizers"],
            ["Burrata Salad", "Fresh burrata with heirloom tomatoes, basil, and balsamic glaze", 16.00, "Appetizers"],
            ["Crispy Calamari", "Tender calamari rings, lightly fried with lemon herb seasoning", 15.00, "Appetizers"],
            // Main Courses
            ["Wagyu Beef Burger", "Premium wagyu patty with caramelized onions, aged cheddar, brioche bun", 28.00, "Main Courses"],
            ["Pan-Seared Salmon", "Atlantic salmon with lemon butter sauce, seasonal vegetables", 32.00, "Main Courses"],
            ["Truffle Pasta", "House-made fettuccine with black truffle cream and parmesan", 26.00, "Main Courses"],
            ["Ribeye Steak", "12oz prime ribeye with herb butter, roasted garlic mashed potatoes", 45.00, "Main Courses"],
            ["Chicken Marsala", "Free-range chicken with wild mushrooms and marsala wine sauce", 24.00, "Main Courses"],
            // Desserts
            ["Tiramisu", "Classic Italian dessert with espresso-soaked ladyfingers and mascarpone", 12.00, "Desserts"],
            ["Chocolate Lava Cake", "Warm chocolate cake with molten center, vanilla ice cream", 14.00, "Desserts"],
            ["Crème Brûlée", "Vanilla bean custard with caramelized sugar crust", 11.00, "Desserts"],
            // Beverages
            ["House Wine (Glass)", "Selection of red or white wine", 12.00, "Beverages"],
            ["Craft Cocktail", "Signature cocktails made with premium spirits", 15.00, "Beverages"],
            ["Sparkling Water", "Premium Italian sparkling water", 6.00, "Beverages"]
        ];
        
        $stmt = $conn->prepare("INSERT INTO menu_items (name, description, price, category) VALUES (?, ?, ?, ?)");
        foreach ($menu_items as $item) {
            $stmt->bind_param("ssds", $item[0], $item[1], $item[2], $item[3]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

$conn->set_charset("utf8mb4");
?>
