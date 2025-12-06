<?php
include 'dataconnection.php';
include 'header_function.php';


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
include 'header_UI.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration</title>
    <link rel="stylesheet" href="donor_design.css">
    <style>
        
    </style>
</head>
<body>
   

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
                    pattern="\d{12}" title="IC must be exactly 12 digits" placeholder="XXXXXXXXXXXX"
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
                    <input type="tel" class="form-control" id="contact" name="contact" placeholder="01X-XXXXXXX"
                           value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="example@example.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address *</label>
                    <input type="text" class="form-control" id="address" name="address" placeholder="123 Main St, City, Country"
                           value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" 
                           required>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="At least 8 characters">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Re-enter your password">
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