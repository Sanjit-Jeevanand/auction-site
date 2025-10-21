<!DOCTYPE html>
<html lang="en">

<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width-device-width,initial-scale=1.0">
        <title>Auction your item></title>
        <!--link to our stylesheet-->
        <!-- <link rel="stylesheet" href="css/main.css"> -->
</head>

<body>

    <section class"wrapper-main">
        <form action="Includes/formhandler.php" method="post">

            <label for="startingprice">Starting price</label>
            <br>
            <input type="text"id="startprice"name="startprice"placeholder="Enter your starting price">
            <br><br>

            <label for="reserveprice">Reserve price</label>
            <br>
            <input type="text"id="reserveprice"name="reserveprice"placeholder="Enter your reserve price">
            <br><br>

            <label for="starttime">Start time:</label>
            <br>
            <input type="date" id="starttime" name="starttime">
            <br><br>

            <label for="endtime">End time:</label>
            <br>
            <input type="date" id="endtime" name="endtime">
            <br><br>
        </form>
    </section>

</body>

</html>