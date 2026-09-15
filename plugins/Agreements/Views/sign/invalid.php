<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Agreements</title>
    <link rel="stylesheet" href="<?php echo base_url("assets/bootstrap/css/bootstrap.min.css"); ?>" />
</head>
<body class="p-5">
    <div class="container">
        <div class="alert alert-danger"><?php echo isset($message) ? $message : app_lang("agreements_invalid_link"); ?></div>
    </div>
</body>
</html>
