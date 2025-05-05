<!DOCTYPE html>
<html>
<head>
    <title>Animasi</title>
    <style>
        .box {
            width: 100px;
            height: 100px;
            background: red;
            position: relative;
            animation: move 3s infinite;
        }

        @keyframes move {
            0%   { left: 0; }
            50%  { left: 200px; }
            100% { left: 0; }
        }
    </style>
</head>
<body>
    <h1>Animasi Box</h1>
    <div class="box"></div>
</body>
</html>
