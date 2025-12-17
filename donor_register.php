<?php
include 'dataconnection.php';
include 'header_function.php';

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $icnumber = trim($_POST['icnumber']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dob = $_POST['dob'];
    

    // Validation
    $required_fields = ['name', 'contact', 'icnumber', 'email', 'password', 'confirm_password', 'dob'];
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error_message = "Please fill in all required fields: " . implode(', ', $missing_fields);
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^\d{12}$/', $icnumber)) {
        $error_message = "IC Number must be exactly 12 digits.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
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
            // Hash password using bcrypt (secure algorithm)
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert into database
            $insert_query = "INSERT INTO donor (Donor_Name, Donor_ContactNumber, Donor_ICNumber, Donor_Email, 
                            Donor_Password, Donor_DOB) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssss", 
                $name, $contact, $icnumber, $email, $hashed_password, $dob
            );
            
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
    <title>Donor Registration - Love Bridge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #dc2626;
            --dark-red: #b91c1c;
            --light-red: #fee2e2;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --medium-gray: #737373;
            --dark-gray: #262626;
            --text-dark: #171717;
            --border-color: #e5e5e5;
            --success-green: #10b981;
            --error-red: #dc2626;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-gray);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .form-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            border-top: 5px solid var(--primary-red);
        }

        .form-title {
            color: var(--primary-red);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 2px solid var(--light-red);
            padding-bottom: 15px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 0.95rem;
        }

        .form-group label.required::after {
            content: " *";
            color: var(--primary-red);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px var(--light-red);
        }

        .form-control.error {
            border-color: var(--primary-red);
        }

        .form-control.success {
            border-color: var(--success-green);
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--medium-gray);
            margin-top: 5px;
            display: block;
        }

        /* Password Requirements */
        .password-requirements {
            background-color: var(--light-gray);
            border-radius: 6px;
            padding: 15px;
            margin-top: 10px;
            border: 1px solid var(--border-color);
        }

        .requirement-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: var(--medium-gray);
        }

        .requirement-item:last-child {
            margin-bottom: 0;
        }

        .requirement-item i {
            margin-right: 8px;
            font-size: 0.8rem;
            width: 16px;
        }

        .requirement-item.valid {
            color: var(--success-green);
        }

        .requirement-item.valid i {
            color: var(--success-green);
        }

        .requirement-item.invalid {
            color: var(--medium-gray);
        }

        .requirement-item.invalid i {
            color: var(--medium-gray);
        }

        .error-message {
            background-color: var(--light-red);
            color: var(--dark-red);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-red);
            font-weight: 500;
        }

        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid var(--success-green);
            font-weight: 500;
        }

        /* Button Container */
        .button-container {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 150px;
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--dark-red), var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            background: var(--border-color);
            color: var(--medium-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .form-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--medium-gray);
        }

        .form-footer a {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .form-container {
                padding: 25px;
            }
            
            .form-title {
                font-size: 1.8rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .button-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 20px;
            }
            
            .form-title {
                font-size: 1.5rem;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 1rem;
            }
        }

        /* Custom select styling */
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23737373' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
            padding-right: 45px;
        }

        /* Password strength indicator */
        .password-strength {
            height: 5px;
            background: var(--light-gray);
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }

        .strength-weak {
            background-color: var(--primary-red);
            width: 25%;
        }

        .strength-medium {
            background-color: #f59e0b;
            width: 50%;
        }

        .strength-strong {
            background-color: var(--success-green);
            width: 75%;
        }

        .strength-very-strong {
            background-color: #059669;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Join Love Bridge - Donor Registration</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="donor_register.php" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="required">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               pattern="[A-Za-z\s]+" title="Only alphabets and spaces allowed"
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required>
                        <span class="form-help">Enter your full name (letters and spaces only)</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="icnumber" class="required">IC Number</label>
                        <input type="text" class="form-control" id="icnumber" name="icnumber"
                               pattern="\d{12}" title="IC must be exactly 12 digits" 
                               placeholder="990101015678"
                               value="<?php echo isset($_POST['icnumber']) ? htmlspecialchars($_POST['icnumber']) : ''; ?>" 
                               required>
                        <span class="form-help">12 digits only (e.g., 990101015678)</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dob" class="required">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" 
                               max="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo isset($_POST['dob']) ? $_POST['dob'] : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact" class="required">Contact Number</label>
                        <input type="tel" class="form-control" id="contact" name="contact" 
                               pattern="01[0-9]-[0-9]{7,8}" title="Format: 01X-XXXXXXX or 01X-XXXXXXXX"
                               placeholder="012-3456789"
                               value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" 
                               required>
                        <span class="form-help">Format: 01X-XXXXXXX</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="required">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="example@example.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>
                </div>

                <!-- Password Section -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="required">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               required placeholder="At least 8 characters">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <!-- Password Requirements -->
                        <div class="password-requirements">
                            <div class="requirement-item invalid" id="lengthReq"><i class="fas fa-times"></i> Must be 8-15 characters long</div>
                            <div class="requirement-item invalid" id="uppercaseReq"><i class="fas fa-times"></i> Must contain at least one Uppercase letter</div>
                            <div class="requirement-item invalid" id="lowercaseReq"><i class="fas fa-times"></i> Must contain at least one Lowercase letter</div>
                            <div class="requirement-item invalid" id="numberReq"><i class="fas fa-times"></i> Must contain at least one Number</div>
                            <div class="requirement-item invalid" id="specialReq"><i class="fas fa-times"></i> Must contain at least one Special character (e.g. !@#)</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="required">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               required placeholder="Re-enter your password">
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="form-group">
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" id="terms" name="terms" required style="margin-top: 5px;">
                        <label for="terms" style="margin: 0; font-size: 0.9rem;">
                            I agree to the <a href="terms.php" target="_blank" style="color: var(--primary-red);">Terms and Conditions</a> 
                            and <a href="privacy.php" target="_blank" style="color: var(--primary-red);">Privacy Policy</a> of Love Bridge Donation System.
                        </label>
                    </div>
                </div>
                
                <div class="button-container">
                    <button type="submit" class="btn" id="submitBtn">Create Donor Account</button>
                </div>
            </form>
            
            <div class="form-footer">
                <p>Already have an account? <a href="donor_login.php">Login here</a></p>
                <p style="font-size: 0.9rem; margin-top: 10px;">
                    By registering, you agree to receive updates about our campaigns and activities.
                </p>
            </div>
        </div>
    </div>

    <script>
        // Client-side validation and enhanced features
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const email = document.getElementById('email');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const icNumber = document.getElementById('icnumber');
            const dobInput = document.getElementById('dob');
            const submitBtn = document.getElementById('submitBtn');
            
            // Password requirement elements
            const lengthReq = document.getElementById('lengthReq');
            const uppercaseReq = document.getElementById('uppercaseReq');
            const lowercaseReq = document.getElementById('lowercaseReq');
            const numberReq = document.getElementById('numberReq');
            const specialReq = document.getElementById('specialReq');
            
            // Set max date for DOB (must be at least 18 years old)
            const today = new Date();
            const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
            dobInput.max = maxDate.toISOString().split('T')[0];
            
            // Password strength checker and requirement validation
            password.addEventListener('input', function() {
                const value = password.value;
                let strength = 0;
                
                // Check requirements
                const hasLength = value.length >= 8 && value.length <= 15;
                const hasUppercase = /[A-Z]/.test(value);
                const hasLowercase = /[a-z]/.test(value);
                const hasNumber = /[0-9]/.test(value);
                const hasSpecial = /[^A-Za-z0-9]/.test(value);
                
                // Update requirement indicators
                updateRequirement(lengthReq, hasLength);
                updateRequirement(uppercaseReq, hasUppercase);
                updateRequirement(lowercaseReq, hasLowercase);
                updateRequirement(numberReq, hasNumber);
                updateRequirement(specialReq, hasSpecial);
                
                // Calculate strength
                if (hasLength) strength++;
                if (hasUppercase) strength++;
                if (hasLowercase) strength++;
                if (hasNumber) strength++;
                if (hasSpecial) strength++;
                
                // Reset classes
                passwordStrengthBar.className = 'password-strength-bar';
                
                // Set strength level
                if (strength <= 1) {
                    passwordStrengthBar.classList.add('strength-weak');
                } else if (strength === 2) {
                    passwordStrengthBar.classList.add('strength-medium');
                } else if (strength === 3 || strength === 4) {
                    passwordStrengthBar.classList.add('strength-strong');
                } else if (strength >= 5) {
                    passwordStrengthBar.classList.add('strength-very-strong');
                }
            });
            
            // Update requirement indicator
            function updateRequirement(element, isValid) {
                if (isValid) {
                    element.classList.remove('invalid');
                    element.classList.add('valid');
                    element.querySelector('i').className = 'fas fa-check';
                } else {
                    element.classList.remove('valid');
                    element.classList.add('invalid');
                    element.querySelector('i').className = 'fas fa-times';
                }
            }
            
            // Real-time validation
            function validateEmail() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email.value && !emailRegex.test(email.value)) {
                    email.classList.add('error');
                    return false;
                } else {
                    email.classList.remove('error');
                    return true;
                }
            }
            
            function validatePassword() {
                if (password.value.length < 8) {
                    password.classList.add('error');
                    return false;
                } else {
                    password.classList.remove('error');
                    return true;
                }
            }
            
            function validateConfirmPassword() {
                if (confirmPassword.value && password.value !== confirmPassword.value) {
                    confirmPassword.classList.add('error');
                    return false;
                } else {
                    confirmPassword.classList.remove('error');
                    return true;
                }
            }
            
            function validateICNumber() {
                const icRegex = /^\d{12}$/;
                if (icNumber.value && !icRegex.test(icNumber.value)) {
                    icNumber.classList.add('error');
                    return false;
                } else {
                    icNumber.classList.remove('error');
                    return true;
                }
            }
            
            // Validate all fields
            function validateForm() {
                let isValid = true;
                
                // Check all required fields
                const requiredFields = form.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('error');
                        isValid = false;
                    } else {
                        field.classList.remove('error');
                    }
                });
                
                // Validate specific fields
                if (!validateEmail()) isValid = false;
                if (!validatePassword()) isValid = false;
                if (!validateConfirmPassword()) isValid = false;
                if (!validateICNumber()) isValid = false;
                
                // Validate terms agreement
                const terms = document.getElementById('terms');
                if (!terms.checked) {
                    alert('You must agree to the Terms and Conditions.');
                    isValid = false;
                }
                
                return isValid;
            }
            
            // Event listeners for real-time validation
            email.addEventListener('blur', validateEmail);
            password.addEventListener('blur', validatePassword);
            confirmPassword.addEventListener('blur', validateConfirmPassword);
            icNumber.addEventListener('blur', validateICNumber);
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                // Validate all fields before submission
                if (!validateForm()) {
                    e.preventDefault();
                    alert('Please correct the errors before submitting.');
                    return;
                }
                
                // Check if email and confirm email match
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return;
                }
                
                // Validate terms agreement
                const terms = document.getElementById('terms');
                if (!terms.checked) {
                    e.preventDefault();
                    alert('You must agree to the Terms and Conditions.');
                    return;
                }
            });
            
            // Auto-format contact number
            const contactInput = document.getElementById('contact');
            contactInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length > 3) {
                    value = value.substring(0, 3) + '-' + value.substring(3);
                }
                if (value.length > 12) {
                    value = value.substring(0, 12);
                }
                
                e.target.value = value;
            });
            
            // Auto-format IC number
            icNumber.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length > 12) {
                    value = value.substring(0, 12);
                }
                
                e.target.value = value;
            });
        });
    </script>
</body>
</html>