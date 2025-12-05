<!DOCTYPE html>
<html>
<head>
    <style>
        /* --- 页脚整体样式 --- */
        .site-footer {
            background-color: #333; /* 深色背景 */
            color: #b3b3b3;         /* 浅灰文字 */
            padding: 70px 0;
            margin-top: 50px;
            font-family: "Arial", sans-serif;
            font-size: 15px;
            line-height: 1.7;
        }

        /* --- 容器 --- */
        .footer-container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap; /* 手机端自动换行 */
            justify-content: space-between;
            gap: 40px;
        }

        /* --- 每一列 --- */
        .footer-col {
            flex: 1;
            min-width: 250px; /* 防止手机上太挤 */
        }

        /* --- 标题样式 --- */
        .footer-heading {
            font-size: 18px;
            color: #fff;
            margin-bottom: 20px;
            font-weight: bold;
            display: inline-block;
            border-bottom: 2px solid #00a651; /* 绿色下划线 */
            padding-bottom: 5px;
        }

        /* --- 链接列表 --- */
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .footer-links li {
            margin-bottom: 10px;
        }
        .footer-links a {
            color: #b3b3b3;
            text-decoration: none;
            transition: 0.3s;
        }
        .footer-links a:hover {
            color: #fff;
            padding-left: 5px; /* 悬停时稍微右移 */
        }

        /* --- 订阅表单 --- */
        .subscribe-form {
            display: flex;
            margin-bottom: 20px;
        }
        .subscribe-form input {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 4px 0 0 4px;
            outline: none;
        }
        .subscribe-form button {
            background-color: #00a651;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-weight: bold;
        }
        .subscribe-form button:hover {
            background-color: #008f45;
        }

        /* --- 社交媒体图标 (用字符代替) --- */
        .social-links a {
            display: inline-block;
            width: 35px;
            height: 35px;
            background: rgba(255,255,255,0.1);
            color: #fff;
            text-align: center;
            line-height: 35px;
            border-radius: 50%;
            margin-right: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }
        .social-links a:hover {
            background: #00a651;
        }

        /* --- 版权信息 --- */
        .copyright-section {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body>

    <footer class="site-footer">
        <div class="footer-container">
            
            <div class="footer-col">
                <h3 class="footer-heading">About Love Bridge</h3>
                <p>Love Bridge is a non-profit organization dedicated to helping those in need. Join us in making the world a better place through your generous contributions.</p>
            </div>

            <div class="footer-col">
                <h3 class="footer-heading">Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="Homepage.php">Home</a></li>
                    <li><a href="About Us.php">About Us</a></li>
                    <li><a href="Contact_us.php">Contact Us</a></li>
                    </ul>
            </div>

            <div class="footer-col">
                <h3 class="footer-heading">Subscribe</h3>
                <p>Subscribe to our newsletter to get latest updates.</p>
                
                <form action="#" class="subscribe-form">
                    <input type="email" placeholder="Enter Email">
                    <button type="submit">Subscribe</button>
                </form>

                <h3 class="footer-heading" style="margin-top: 20px; font-size: 16px;">Follow Us</h3>
                <div class="social-links">
                    <a href="#" title="Facebook">f</a>
                    <a href="#" title="Twitter">t</a>
                    <a href="#" title="Instagram">in</a>
                </div>
            </div>

        </div>

        <div class="footer-container">
            <div class="copyright-section" style="width: 100%;">
                Copyright &copy; 2025 All rights reserved | Love Bridge
            </div>
        </div>
    </footer>

</body>
</html>