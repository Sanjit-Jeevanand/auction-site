<!DOCTYPE html>
<html lang="en">

<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width-device-width,initial-scale=1.0">
        <title>Add items></title>
        <!--link to our stylesheet-->
        <!-- <link rel="stylesheet" href="css/main.css"> -->
</head>


<body>

    <section class"wrapper-main">
        <form action="Includes/formhandler.php" method="post">

            <label for="itemname">Item name</label>
            <br>
            <input type="text"id="itemname"name="itemname"placeholder="Item name">
            <br><br>

            <label for="message">Description</label>
            <br>
            <textarea name="message" id="message" placeholder="Write a description for your item ...."style="width:100%,"></textarea>
            <br><br>

            <label>Condition</label>
            <br>
            <input type="radio",id="brandnewitem"name="itemcondition"value="BrandNew">
            <label for="message">Brand new</label>
            <input type="radio",id="likenewitem"name="itemcondition"value="LikeNew">
            <label for="message">Like new</label>
            <input type="radio",id="gooditem"name="itemcondition"value="Good">
            <label for="message">Good</label>
            <br><br>
            <button type="submit"value="submit">Send data</button>

            <!-- figure out how to submit image -->

            <!-- <label for="itemimage">Item image</label>
            <br>
            <input type="text"id="itemimage"name="itemimage"placeholder="Attach your a picture of your time">
            <br><br>

            <input type="image" src="img_submit.gif" alt="Submit" width="48" height="48"> -->

        </form>
    </section>

</body>

</html>