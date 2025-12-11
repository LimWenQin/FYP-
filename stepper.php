<style>
    /* 进度条容器 */
    .stepper-container {
        width: 100%;
        max-width: 1140px; /* 与 Bootstrap container 宽度一致 */
        margin: 0 auto;
        padding: 30px 15px;
    }

    .stepper-wrapper {
        display: flex;
        justify-content: space-between;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        position: relative;
    }

    .stepper-item {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        z-index: 2;
    }

    /* 连接线 */
    .stepper-item::before {
        content: "";
        position: absolute;
        top: 18px; /* 圆圈的一半高度 */
        left: -50%;
        width: 100%;
        height: 3px;
        background-color: #e0e0e0;
        z-index: -1;
    }
    
    .stepper-item:first-child::before {
        content: none; /* 第一个圆圈左边没有线 */
    }

    /* 圆圈样式 */
    .step-counter {
        position: relative;
        z-index: 5;
        display: flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #e0e0e0;
        color: #999;
        font-weight: bold;
        margin-bottom: 8px;
        font-size: 16px;
        transition: 0.3s all ease;
    }

    /* 文字标签 */
    .step-name {
        color: #999;
        font-size: 13px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
    }

    /* --- 状态：Active (当前步骤 - 红色) --- */
    .stepper-item.active .step-counter {
        background-color: #dc2626;
        border-color: #dc2626;
        color: #fff;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
    }
    .stepper-item.active .step-name {
        color: #dc2626;
        font-weight: bold;
    }

    /* --- 状态：Completed (已完成 - 绿色) --- */
    .stepper-item.completed .step-counter {
        background-color: #28a745;
        border-color: #28a745;
        color: #fff;
    }
    .stepper-item.completed .step-name {
        color: #28a745;
    }
    /* 只有当后一个步骤也是 completed 或 active 时，连接线才变色 */
    .stepper-item.completed + .stepper-item.completed::before,
    .stepper-item.completed + .stepper-item.active::before {
        background-color: #28a745;
    }
</style>

<?php
// 默认参数设置，防止报错
if (!isset($current_step)) { $current_step = 1; }
if (!isset($flow_type)) { $flow_type = 'standard'; } // 'standard' (4步) 或 'special' (3步)
?>

<div class="stepper-container">
    <div class="stepper-wrapper">
        
        <div class="stepper-item <?php echo ($current_step > 1) ? 'completed' : ($current_step == 1 ? 'active' : ''); ?>">
            <div class="step-counter"><?php echo ($current_step > 1) ? '✓' : '1'; ?></div>
            <div class="step-name">Details</div>
        </div>

        <?php if ($flow_type == 'standard'): ?>
        <div class="stepper-item <?php echo ($current_step > 2) ? 'completed' : ($current_step == 2 ? 'active' : ''); ?>">
            <div class="step-counter"><?php echo ($current_step > 2) ? '✓' : '2'; ?></div>
            <div class="step-name">Branch</div>
        </div>
        <?php endif; ?>

        <?php 
            // 如果是 Standard，这是第3步；如果是 Special，这是第2步
            $pay_step_logic = ($flow_type == 'standard') ? 3 : 2; 
            $pay_step_display = ($flow_type == 'standard') ? '3' : '2';
        ?>
        <div class="stepper-item <?php echo ($current_step > $pay_step_logic) ? 'completed' : ($current_step == $pay_step_logic ? 'active' : ''); ?>">
            <div class="step-counter"><?php echo ($current_step > $pay_step_logic) ? '✓' : $pay_step_display; ?></div>
            <div class="step-name">Payment</div>
        </div>

        <?php 
            $done_step_logic = ($flow_type == 'standard') ? 4 : 3; 
            $done_step_display = ($flow_type == 'standard') ? '4' : '3';
        ?>
        <div class="stepper-item <?php echo ($current_step == $done_step_logic) ? 'active' : ''; ?>"> <div class="step-counter"><?php echo $done_step_display; ?></div>
            <div class="step-name">Complete</div>
        </div>

    </div>
</div>