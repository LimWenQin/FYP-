<!-- footer.php -->
<footer class="footer">
    <div class="footer-links">
        <a href="privacy.php">Privacy Policy</a>
    </div>
    <div class="footer-links">
        <a href="Contact_Us.php">Contact Us</a>
        <a href="about us.html">About Us</a>
    </div>
    <div class="footer-links">
        <a href="terms.php">Terms & Conditions</a>
    </div>
</footer>

<style>
    /* Footer Styles */
    .footer {
        background-color: #F6B8B8;
        padding: 20px 50px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
    }
    
    .footer-links {
        display: flex;
        gap: 20px;
    }
    
    .footer-links a {
        color: #4A4A4A;
        text-decoration: none;
        font-weight: bold;
        transition: opacity 0.3s;
    }
    
    .footer-links a:hover {
        opacity: 0.8;
    }
    
    @media (max-width: 768px) {
        .footer {
            padding: 15px 20px;
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
    }
</style>