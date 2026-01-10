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
            border-bottom: 2px solid #c02a01ff; /* 绿色下划线 */
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

        /* --- 版权信息 --- */
        .copyright-section {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
            color: #777;
        }
        
        /* [新增] 版权栏链接的样式 */
        .copyright-section a {
            color: #777; /* 与版权文字颜色一致 */
            text-decoration: none;
            margin: 0 15px; /* 左右间距 */
            transition: 0.3s;
        }
        .copyright-section a:hover {
            color: #fff; /* 悬停变白 */
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
                    <li><a href="About_us.php">About Us</a></li>
                    <li><a href="Contact_us.php">Contact Us</a></li>
                    <li><a href="FAQs.php">FAQ</a></li>
                    </ul>
            </div>

            <div class="footer-col">
               <div class="footer-col">
                    <h3 class="footer-heading">Contact Us</h3>
                    <ul class="footer-links">
                        <li style="color: #b3b3b3; margin-bottom: 15px;">
                            <span style="color: #fff; font-weight:bold;">Address:</span><br>
                            123, Jalan Love Bridge,<br>
                            75450 Melaka, Malaysia
                        </li>
                        <li>
                            <span style="color: #fff; font-weight:bold;">Phone:</span><br>
                            <a href="https://wa.me/601111190233" target="_blank">+60 11-1119 0233</a>
                        </li>
                        <li>
                            <span style="color: #fff; font-weight:bold;">Email:</span><br>
                            <a href="mailto:lovebridge1201@gmail.com">lovebridge1201@gmail.com</a>
                        </li>
                    </ul>
                </div>
            </div>

        </div>

        <div class="footer-container">
            <div class="copyright-section" style="width: 100%;">
                <a href="terms&condition.php">Terms & Conditions</a>
                |
                <span style="margin: 0 10px;">Copyright &copy; 2025 All rights reserved | Love Bridge</span>
                |
                <a href="privacy.php">Privacy Policy</a>
            </div>
        </div>
    </footer>

</body>
</html>