<?php
include 'dataconnection.php';
include 'header_function.php';

// SweetAlert2 的标记变量
$registration_success = false;
$error_message = "";

// Initialize variables
$name = $contact = $email = $dob = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST['name']);
    
    // --- 处理电话号码 ---
    // 逻辑保持不变：用户输入不带0，后台补0
    $raw_contact = trim($_POST['contact']);
    $contact = '0' . $raw_contact; 

    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dob = $_POST['dob'];

    // Validation
    $required_fields = ['name', 'contact', 'email', 'password', 'confirm_password', 'dob'];
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error_message = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        
        // --- 检查 Email ---
        $check_query = "SELECT Donor_ID, Is_Deleted FROM donor WHERE Donor_Email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $account_exists = false;
        $is_deleted_account = false;
        $existing_id = null;

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $existing_id = $row['Donor_ID'];
            
            if ($row['Is_Deleted'] == 0) {
                $account_exists = true;
            } else {
                $is_deleted_account = true;
            }
        }

        if ($account_exists) {
            $error_message = "Email already exists.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt_action = null;

            if ($is_deleted_account) {
                // Update
                $update_query = "UPDATE donor SET 
                                 Donor_Name = ?, 
                                 Donor_ContactNumber = ?, 
                                 Donor_Password = ?, 
                                 Donor_DOB = ?, 
                                 Is_Deleted = 0 
                                 WHERE Donor_ID = ?";
                $stmt_action = $conn->prepare($update_query);
                $stmt_action->bind_param("ssssi", $name, $contact, $hashed_password, $dob, $existing_id);

            } else {
                // Insert
                $insert_query = "INSERT INTO donor (Donor_Name, Donor_ContactNumber, Donor_Email, 
                                 Donor_Password, Donor_DOB, Is_Deleted) 
                                 VALUES (?, ?, ?, ?, ?, 0)";
                $stmt_action = $conn->prepare($insert_query);
                $stmt_action->bind_param("sssss", $name, $contact, $email, $hashed_password, $dob);
            }
            
            if ($stmt_action->execute()) {
                // --- 注册成功 ---
                $registration_success = true;
                $name = $contact = $email = $dob = ""; 
            } else {
                $error_message = "Error: " . $stmt_action->error;
            }
            $stmt_action->close();
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            position: relative;
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
            background-color: #fff5f5;
        }

        .form-control.success {
            border-color: var(--success-green);
        }
        
        /* --- Phone Group (+60) --- */
        .phone-group {
            display: flex;
            align-items: stretch;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .phone-group:focus-within {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px var(--light-red);
        }

        .phone-group.error {
            border-color: var(--primary-red);
            background-color: #fff5f5;
        }

        .phone-prefix {
            background-color: #e5e5e5;
            color: var(--dark-gray);
            padding: 0 15px;
            display: flex;
            align-items: center;
            font-weight: 600;
            border-right: 1px solid #d4d4d4;
            user-select: none;
        }

        .phone-group .form-control {
            border: none;
            border-radius: 0;
            box-shadow: none;
            background-color: transparent;
        }
        
        .phone-group .form-control:focus {
            box-shadow: none;
        }

        /* --- New: Password Toggle Styles --- */
        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper .form-control {
            padding-right: 45px; /* Space for the eye icon */
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--medium-gray);
            z-index: 10;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: var(--primary-red);
        }
        /* ------------------------------------- */

        .field-error-msg {
            color: var(--error-red);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }
        
        .field-error-msg.visible {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .field-info-msg {
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }

        .field-info-msg.match {
            color: var(--success-green);
            display: block;
        }
        
        .field-info-msg.mismatch {
            color: var(--error-red);
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
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

        .requirement-item:last-child { margin-bottom: 0; }
        .requirement-item i { margin-right: 8px; font-size: 0.8rem; width: 16px; }
        .requirement-item.valid { color: var(--success-green); }
        .requirement-item.valid i { color: var(--success-green); }
        .requirement-item.invalid { color: var(--medium-gray); }
        .requirement-item.invalid i { color: var(--medium-gray); }

        .error-message {
            background-color: var(--light-red);
            color: var(--dark-red);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-weight: 500;
        }

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

        .form-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--medium-gray);
        }

        .form-footer a { color: var(--primary-red); text-decoration: none; font-weight: 600; }
        .form-footer a:hover { text-decoration: underline; }

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
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-weak { background-color: var(--primary-red); width: 25%; }
        .strength-medium { background-color: #f59e0b; width: 50%; }
        .strength-strong { background-color: var(--success-green); width: 75%; }
        .strength-very-strong { background-color: #059669; width: 100%; }
        
        @media (max-width: 768px) {
            .container { margin: 20px auto; padding: 0 15px; }
            .form-container { padding: 25px; }
            .form-title { font-size: 1.8rem; }
            .form-row { grid-template-columns: 1fr; gap: 20px; }
            .button-container { flex-direction: column; gap: 15px; }
            .btn { width: 100%; }
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
            
            <form method="POST" action="donor_register.php" id="registerForm" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="required">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               pattern="[A-Za-z\s]+" 
                               value="<?php echo htmlspecialchars($name); ?>" 
                               required>
                        <span class="form-help">Enter your full name (letters and spaces only)</span>
                        <div class="field-error-msg" id="nameError">Full Name is required.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dob" class="required">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" 
                               value="<?php echo htmlspecialchars($dob); ?>" 
                               required>
                               <span class="form-help">Must be 18 years old and above</span>
                        <div class="field-error-msg" id="dobError">Date of Birth is required.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact" class="required">Phone Number</label>
                        <div class="phone-group" id="phoneGroup">
                            <div class="phone-prefix">+60</div>
                            <input type="tel" class="form-control" id="contact" name="contact" 
                                   placeholder="12-3456789"
                                   value="<?php echo htmlspecialchars($contact); ?>" 
                                   required>
                        </div>
                        <span class="form-help">Phone number must start with 1 (e.g. 12-3456789)</span>
                        <div class="field-error-msg" id="contactError">Contact Number is required.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email" class="required">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="example@example.com"
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required>
                        <div class="field-error-msg" id="emailError">Email Address is required.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="required">Password</label>
                        <div class="password-wrapper">
                            <input type="password" class="form-control" id="password" name="password" 
                                   required placeholder="At least 8 characters">
                            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement-item invalid" id="lengthReq"><i class="fas fa-times"></i> Must be 8-15 characters long</div>
                            <div class="requirement-item invalid" id="uppercaseReq"><i class="fas fa-times"></i> Must contain at least one Uppercase letter</div>
                            <div class="requirement-item invalid" id="lowercaseReq"><i class="fas fa-times"></i> Must contain at least one Lowercase letter</div>
                            <div class="requirement-item invalid" id="numberReq"><i class="fas fa-times"></i> Must contain at least one Number</div>
                            <div class="requirement-item invalid" id="specialReq"><i class="fas fa-times"></i> Must contain at least one Special character (e.g. !@#)</div>
                        </div>
                          <div class="field-error-msg" id="passwordError">Password is required.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="required">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required placeholder="Re-enter your password">
                            <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                        </div>
                        <div class="field-info-msg" id="matchMsg"></div>
                        <div class="field-error-msg" id="confirmPasswordError">Please confirm your password.</div>
                    </div>
                </div>

                <div class="form-group">
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" id="terms" name="terms" required style="margin-top: 5px;">
                        <label for="terms" style="margin: 0; font-size: 0.9rem;">
                            I agree to the <a href="terms&condition.php" target="_blank" style="color: var(--primary-red);">Terms and Conditions</a> 
                            and <a href="privacy.php" target="_blank" style="color: var(--primary-red);">Privacy Policy</a> of Love Bridge Donation System.
                        </label>
                    </div>
                    <div class="field-error-msg" id="termsError">You must agree to the Terms and Conditions to proceed.</div>
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
 <?php include 'footer.php'; ?>

    <script>
        // --- SweetAlert2 Success Logic ---
        <?php if ($registration_success): ?>
        Swal.fire({
            title: 'Registration Successful!',
            text: 'Your account has been created successfully.',
            icon: 'success',
            confirmButtonText: 'Login Now',
            confirmButtonColor: '#dc2626', 
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'donor_login.php';
            }
        });
        <?php endif; ?>
        // ---------------------------------

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const email = document.getElementById('email');
            const dobInput = document.getElementById('dob');
            const nameInput = document.getElementById('name');
            const contactInput = document.getElementById('contact');
            const phoneGroup = document.getElementById('phoneGroup'); 
            const termsInput = document.getElementById('terms');
            
            // --- 1. Password Visibility Toggle Logic (New Added) ---
            function setupPasswordToggle(toggleId, inputId) {
                const toggleIcon = document.getElementById(toggleId);
                const inputField = document.getElementById(inputId);

                if (toggleIcon && inputField) {
                    toggleIcon.addEventListener('click', function() {
                        // Toggle input type
                        const type = inputField.getAttribute('type') === 'password' ? 'text' : 'password';
                        inputField.setAttribute('type', type);
                        
                        // Toggle icon class
                        this.classList.toggle('fa-eye');
                        this.classList.toggle('fa-eye-slash');
                    });
                }
            }
            setupPasswordToggle('togglePassword', 'password');
            setupPasswordToggle('toggleConfirmPassword', 'confirm_password');
            // --------------------------------------------------------

            function setFieldState(elementId, isError, message = "") {
                const el = document.getElementById(elementId);
                // Handle Contact Input special styling (apply error to the group div)
                if(elementId === 'contactError') {
                    if (isError) {
                        phoneGroup.classList.add('error');
                        el.classList.add('visible');
                        if(message) el.innerText = message;
                    } else {
                        phoneGroup.classList.remove('error');
                        el.classList.remove('visible');
                    }
                    return;
                }

                if (isError) {
                    el.classList.add('visible');
                    if(message) el.innerText = message;
                } else {
                    el.classList.remove('visible');
                }
            }

            // --- 2. Password Match Logic ---
            function checkPasswordMatch() {
                const matchMsg = document.getElementById('matchMsg');
                const confirmError = document.getElementById('confirmPasswordError');
                const pass = password.value;
                const confirm = confirmPassword.value;

                if (confirm.length === 0) {
                    matchMsg.className = 'field-info-msg';
                    matchMsg.innerText = '';
                    confirmPassword.classList.remove('error', 'success');
                    return false;
                }

                confirmError.classList.remove('visible');

                if (pass === confirm) {
                    matchMsg.className = 'field-info-msg match';
                    matchMsg.innerHTML = '<i class="fas fa-check"></i> Passwords match';
                    confirmPassword.classList.remove('error');
                    confirmPassword.classList.add('success');
                    return true;
                } else {
                    matchMsg.className = 'field-info-msg mismatch';
                    matchMsg.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                    confirmPassword.classList.add('error');
                    confirmPassword.classList.remove('success');
                    return false;
                }
            }

            confirmPassword.addEventListener('input', checkPasswordMatch);
            password.addEventListener('input', checkPasswordMatch);

            // --- 3. Validation Functions ---
            function validateAge() {
                const dobValue = dobInput.value;
                if (!dobValue) return true; 

                const today = new Date();
                const birthDate = new Date(dobValue);
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }

                if (age < 18) {
                    dobInput.classList.add('error');
                    setFieldState('dobError', true, "Must be 18 years old and above.");
                    return false;
                } else {
                    dobInput.classList.remove('error');
                    setFieldState('dobError', false);
                    return true;
                }
            }
            dobInput.addEventListener('change', validateAge);

            function validateEmail() {
                if(!email.value) return true; 
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email.value)) {
                    email.classList.add('error');
                    setFieldState('emailError', true, "Please enter a valid email address.");
                    return false;
                } else {
                    email.classList.remove('error');
                    setFieldState('emailError', false);
                    return true;
                }
            }
            email.addEventListener('blur', validateEmail);

            function validateName() {
                if(!nameInput.value) return true; 
                const nameRegex = /^[A-Za-z\s]+$/;
                if (!nameRegex.test(nameInput.value)) {
                    nameInput.classList.add('error');
                    setFieldState('nameError', true, "Name must contain letters only.");
                    return false;
                } else {
                    nameInput.classList.remove('error');
                    setFieldState('nameError', false);
                    return true;
                }
            }
            nameInput.addEventListener('input', validateName);
            
            // ============================================================
            // --- 【修改】 Contact Logic - 与 Profile Page 一致 ---
            // ============================================================
            
            // 1. 输入监听：自动格式化 (XX-XXXXXXX) 并禁止首位输 0
            contactInput.addEventListener('input', function(e) {
                let val = e.target.value.replace(/\D/g, ''); // 移除非数字

                // 如果用户尝试输入 0 开头，移除它
                if (val.startsWith('0')) {
                    val = val.substring(1);
                }

                // 自动添加横杠
                if (val.length > 2) val = val.substring(0, 2) + '-' + val.substring(2);
                if (val.length > 11) val = val.substring(0, 11); // 限制最大长度 (2位 + 1横杠 + 8位 = 11字符)

                e.target.value = val;
                
                // 实时移除错误提示 (如果有输入)
                if(val.length >= 2) {
                    const currentError = document.getElementById('contactError').innerText;
                    if(currentError.includes("Phone number must start with 1")) {
                         setFieldState('contactError', false);
                    }
                }
            });

            // 2. 验证逻辑：检查是否以 1 开头，且长度符合要求
            function validateContact() {
                 if(!contactInput.value) return true;
                 
                 const rawValue = contactInput.value.replace(/\D/g, '');

                 // Check 1: Must start with 1
                 if (rawValue.length > 0 && rawValue.charAt(0) !== '1') {
                     phoneGroup.classList.add('error');
                     setFieldState('contactError', true, "Phone number must start with 1 (e.g. 12-3456789).");
                     return false;
                 }
                 
                 // Check 2: Length (9 to 10 digits excluding the implicit 0)
                 if(rawValue.length < 9 || rawValue.length > 10) {
                      phoneGroup.classList.add('error');
                      setFieldState('contactError', true, "Invalid length. Please check your number.");
                      return false;
                 } else {
                      phoneGroup.classList.remove('error');
                      setFieldState('contactError', false);
                      return true;
                 }
            }
            contactInput.addEventListener('blur', validateContact);
            // ============================================================


            // --- Password Strength UI ---
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const reqs = {
                length: document.getElementById('lengthReq'),
                upper: document.getElementById('uppercaseReq'),
                lower: document.getElementById('lowercaseReq'),
                number: document.getElementById('numberReq'),
                special: document.getElementById('specialReq')
            };

            password.addEventListener('input', function() {
                const value = password.value;
                
                if (value.length === 0) {
                    passwordStrengthBar.className = 'password-strength-bar'; 
                    passwordStrengthBar.style.width = '0%';
                    
                    for(let key in reqs) {
                        updateRequirement(reqs[key], false);
                    }
                    return; 
                }

                passwordStrengthBar.style.width = ''; 

                let strength = 0;
                const checks = {
                    length: value.length >= 8 && value.length <= 15,
                    upper: /[A-Z]/.test(value),
                    lower: /[a-z]/.test(value),
                    number: /[0-9]/.test(value),
                    special: /[^A-Za-z0-9]/.test(value)
                };
                
                for(let key in checks) {
                    updateRequirement(reqs[key], checks[key]);
                    if(checks[key]) strength++;
                }
                
                passwordStrengthBar.className = 'password-strength-bar';
                if (strength <= 1) passwordStrengthBar.classList.add('strength-weak');
                else if (strength === 2) passwordStrengthBar.classList.add('strength-medium');
                else if (strength === 3 || strength === 4) passwordStrengthBar.classList.add('strength-strong');
                else if (strength >= 5) passwordStrengthBar.classList.add('strength-very-strong');
            });

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

            // --- 4. FINAL SUBMISSION HANDLER ---
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // A. Check Empty Fields
                const requiredInputs = [
                    { input: nameInput, errorId: 'nameError', msg: 'Full Name is required.' },
                    { input: dobInput, errorId: 'dobError', msg: 'Date of Birth is required.' },
                    { input: email, errorId: 'emailError', msg: 'Email Address is required.' },
                    { input: password, errorId: 'passwordError', msg: 'Password is required.' },
                    { input: confirmPassword, errorId: 'confirmPasswordError', msg: 'Please confirm your password.' }
                ];

                requiredInputs.forEach(item => {
                    if (!item.input.value.trim()) {
                        item.input.classList.add('error');
                        setFieldState(item.errorId, true, item.msg);
                        isValid = false;
                    } else {
                         item.input.classList.remove('error');
                         setFieldState(item.errorId, false);
                    }
                });
                
                // Special check for Contact
                if(!contactInput.value.trim()) {
                    phoneGroup.classList.add('error');
                    setFieldState('contactError', true, 'Contact Number is required.');
                    isValid = false;
                } else {
                    if(!validateContact()) isValid = false;
                }

                if (!validateAge()) isValid = false;
                if (!validateEmail()) isValid = false;
                if (!validateName()) isValid = false;
                
                if (confirmPassword.value.trim() !== "") {
                    if (!checkPasswordMatch()) isValid = false;
                }
                
                const invalidReqs = document.querySelectorAll('.requirement-item.invalid');
                if (invalidReqs.length > 0 && password.value) {
                    password.classList.add('error');
                    setFieldState('passwordError', true, "Please meet all password requirements.");
                    isValid = false;
                }

                if (!termsInput.checked) {
                    setFieldState('termsError', true);
                    isValid = false;
                } else {
                    setFieldState('termsError', false);
                }

                if (!isValid) {
                    e.preventDefault(); 
                    const firstError = document.querySelector('.form-control.error, .field-error-msg.visible');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (firstError.tagName === 'INPUT') firstError.focus();
                        else {
                             const prevInput = firstError.parentElement.querySelector('input');
                             if(prevInput) prevInput.focus();
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>