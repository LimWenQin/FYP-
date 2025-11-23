<?php
session_start();

include 'dataconnection.php';

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $contact = trim($_POST['contact']);
    $icnumber = trim($_POST['icnumber']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = trim($_POST['address']);
    $dob = $_POST['dob'];
    $description = trim($_POST['description']);

    // Validation
    if (empty($fname) || empty($lname) || empty($contact) || empty($icnumber) || 
        empty($email) || empty($password) || empty($confirm_password) || 
        empty($address) || empty($dob)) {
        $error_message = "All required fields must be filled.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Check if email or IC number already exists
        $check_query = "SELECT Donor_ID FROM donor WHERE Donor_Email = ? OR Donor_ICNumber = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ss", $email, $icnumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Email or IC Number already exists.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into database
            $insert_query = "INSERT INTO donor (Donor_FName, Donor_LName, Donor_ContactNumber, Donor_ICNumber, Donor_Email, Donor_Password, Donor_Address, Donor_DOB, Donor_Description) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("sssssssss", $fname, $lname, $contact, $icnumber, $email, $hashed_password, $address, $dob, $description);
            
            if ($stmt->execute()) {
                $success_message = "Registration successful! You can now login.";
                header("Location: donor_login.php");
                exit();

            } else {
                $error_message = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration</title>
    <link rel="stylesheet" href="donor_design.css">
    <style>
        :root {
            --primary: #F6B8B8;
            --secondary: #FFF5E4;
            --text: #4A4A4A;
            --light-text: #777777;
            --white: #FFFFFF;
            --shadow: rgba(0, 0, 0, 0.1);
            --error: #e74c3c;
            --success: #2ecc71;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--secondary);
            color: var(--text);
            line-height: 1.6;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary);
            padding: 20px 50px;
            box-shadow: 0 2px 6px var(--shadow);
        }
        
        .logo {
            font-weight: bold;
            font-size: 36px;
            color: var(--text);
        }
        
        .header .function-links a {
            margin-left: 15px;
            text-decoration: none;
            font-size: 20px;
            color: var(--text);
            font-weight: bold;
            transition: opacity 0.3s;
        }
        
        .header .function-links a:hover {
            opacity: 0.8;
        }
        
        .search {
            padding: 6px;
            font-size: 16px;
            border: 1px solid var(--primary);
            border-radius: 4px;
            outline: none;
        }
        
        .container {
            display: flex;
            flex-direction: row;
            padding: 20px;
            min-height: calc(100vh - 120px);
            align-items: center;
            justify-content: center;
        }
        
        .form-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            padding: 30px;
            width: 100%;
            max-width: 600px;
            margin: 20px;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text);
            font-size: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(246, 184, 184, 0.3);
            outline: none;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        
        .btn:hover {
            background-color: #f0a8a8;
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            color: var(--error);
            font-size: 14px;
            margin-top: 5px;
            padding: 8px;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 4px;
            display: <?php echo !empty($error_message) ? 'block' : 'none'; ?>;
        }
        
        .success-message {
            color: var(--success);
            font-size: 14px;
            margin-top: 5px;
            padding: 8px;
            background-color: rgba(46, 204, 113, 0.1);
            border-radius: 4px;
            display: <?php echo !empty($success_message) ? 'block' : 'none'; ?>;
        }
        
        .row {
            display: flex;
            gap: 15px;
        }
        
        .row .form-group {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .logo {
                font-size: 28px;
            }
            
            .container {
                padding: 10px;
            }
            
            .form-container {
                margin: 10px;
                padding: 20px;
            }
            
            .row {
                flex-direction: column;
                gap: 0;
            }
        }*/
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">Donor Platform</div>
        <div class="function-links">
            <a href="homepage.php">Home</a>
            <a href="about.php">About Us</a>
            <a href="projects.php">Donation Projects</a>
            <a href="contact.php">Contact</a>
        </div>
        <input type="text" class="search" placeholder="Search...">
    </header>

    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Create Donor Account</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="donor_register.php" id="registerForm">
                <div class="row">
                    <div class="form-group">
                        <label for="fname">First Name *</label>
                        <input type="text" class="form-control" id="fname" name="fname" 
                         pattern="[A-Za-z]+" title="Only alphabets allowed"
                         value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>" 
                         required>

                    </div>
                    <div class="form-group">
                        <label for="lname">Last Name *</label>
                        <input type="text" class="form-control" id="lname" name="lname" 
                        pattern="[A-Za-z]+" title="Only alphabets allowed"
                        value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>" 
                        required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="icnumber">IC Number *</label>
                    <input type="text" class="form-control" id="icnumber" name="icnumber"
                    pattern="\d{12}" title="IC must be exactly 12 digits" placeholder="123456789012"
                    value="<?php echo isset($_POST['icnumber']) ? htmlspecialchars($_POST['icnumber']) : ''; ?>" 
                    required>

                </div>
                
                <div class="form-group">
                    <label for="dob">Date of Birth *</label>
                    <input type="date" class="form-control" id="dob" name="dob" 
                           value="<?php echo isset($_POST['dob']) ? $_POST['dob'] : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="contact">Contact Number *</label>
                    <input type="tel" class="form-control" id="contact" name="contact" 
                           value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address *</label>
                    <input type="text" class="form-control" id="address" name="address" 
                           value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" 
                           required>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              placeholder="Tell us about yourself..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn">Register</button>

            </form>
            
            <div class="form-footer">
                <p>Already have an account? <a href="donor_login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        // Client-side validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            let isValid = true;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check password length
            if (password.length < 8) {
                alert('Password must be at least 8 characters long.');
                isValid = false;
            }
            
            // Check if passwords match
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>