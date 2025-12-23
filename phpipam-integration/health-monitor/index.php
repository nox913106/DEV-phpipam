<?php
/**
 * Health Monitor Tool for phpIPAM
 * 
 * This file should be placed at: /phpipam/app/tools/health-monitor/index.php
 * 
 * @author Jason Cheng
 * @version 2.1
 * @date 2025-12-23
 */

# verify that user is logged in
$User->check_user_session();
?>

<h4><i class="fa fa-heartbeat"></i> Health Monitor Dashboard</h4>
<hr>
<div style="width:100%; height:calc(100vh - 180px); min-height:600px;">
    <iframe src="/health_dashboard/" 
            style="width:100%; height:100%; border:none; border-radius:8px;"
            title="Health Monitor Dashboard">
    </iframe>
</div>
