<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';
?>

<?php
    if(isset($_POST['submit'])){

        $uploadedImages = $_FILES['images'];
        $item_name = trim($_POST['title'] ?? '');
        $item_desc = trim($_POST['description'] ?? '');
        $item_condition = trim($_POST['condition'] ?? '');
        $item_category = trim($_POST['category'] ?? '');
        $item_seller = current_user_id();

        date_default_timezone_set('Europe/London');
        $item_timestamp = date('Y-m-d H:i:s');

        $sql_get_category_id = "SELECT category_id from categories where name = :category_name";

        $stmt_category = $pdo->prepare($sql_get_category_id);
        $stmt_category->execute(['category_name' => $item_category]);
        $category_row = $stmt_category->fetch(PDO::FETCH_ASSOC);

        $category_id = $category_row['category_id'];

        if (!$category_row){
            echo "Error: category '{$category_id}' not found";
            return;
        }

        $sql_insert_item = "INSERT INTO items 
        (seller_id, category_id, title, description, `condition`,created_at) 
        values (:seller_id, :category_id, :title, :description, :condition, :created_at)";

        $stmt_item = $pdo -> prepare($sql_insert_item);

        $item_params = [
            'seller_id' => $item_seller,
            'category_id' => $category_id,
            'title' => $item_name,
            'description' => $item_desc,
            'condition' => $item_condition,
            'created_at' => $item_timestamp
        ];

        $stmt_item->execute($item_params);

        $current_item_id = $pdo->lastInsertId();
        $sql_insert_image= "INSERT INTO item_images 
        (item_id,url, display_order) 
        values (:item_id,:url,:display_order)";

        $stmt_image = $pdo->prepare($sql_insert_image);
        $upload_success = true;

        foreach ($uploadedImages['name'] as $key => $value) {
            $targetDir = "Images/";
            $fileName = basename($uploadedImages['name'][$key]);
            $targetFilePath = $targetDir . $fileName;
            $display_order = $key + 1;

            if (file_exists($targetFilePath))
            {
                echo "Sorry, file already exists . <br>";
            }
            else 
            {
                if (move_uploaded_file($uploadedImages["tmp_name"][$key], $targetFilePath))
                {
                    $imagePath = $targetFilePath;  

                    $image_params = [
                        'item_id'       => $current_item_id,
                        'url'           => $targetFilePath,
                        'display_order' => $display_order
                    ];
                    $stmt_image->execute($image_params);
                }
                else
                {
                    echo "<h2>File upload '$item_name' failed </h2>";
                    $upload_success = false;
                 }
            }
        } 
        if ($upload_success && $current_item_id)    {
            header("Location: /auction-site/Pages/seller_items.php");
            exit;

        }  else {
            echo "An error occured during item submission or uploading image";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width-device-width,initial-scale=1.0">
        <style>
            .wrapper-main{
                max-width: 500px;
                margin-left: 100px;
            }
        </style>
        <title>Add items</title>
</head>


<body>

    <section class="wrapper-main">
        <form action="create_item.php" method="post" enctype="multipart/form-data">

            <label for="itemname">Item name</label>
            <br>
            <input type="text"id="title"name="title"placeholder="Item name">
            <br></br>

            <label for="message">Description</label>
            <br>
            <textarea name="description" id="description" placeholder="Write a description for your item ...."style="width:100%,"></textarea>
            <br><br>

            <label>Condition</label>
            <br>
            <input type="radio",id="new"name="condition"value="new">
            <label for="message">Brand new</label>
            <input type="radio",id="likenew"name="condition"value="like new">
            <label for="message">Like new</label>
            <input type="radio",id="used"name="condition"value="used">
            <label for="message">Used</label>
            <input type="radio",id="refurbished"name="condition"value="refurbished">
            <label for="message">Refurbished</label>
            <br></br>

            <label>Category</label>
            <br><br>
            <select name="category">
                <option>Computers</option>
                <option>Mobile Devices</option>
                <option>Gaming</option>
                <option>Console systems</option>
                <option>Video games</option>
                <option>Console systems</option>
                <option>Watches</option>
                <option>Jewelry</option>
            </select>
            <br></br>
            <input type="file" name="images[]" multiple/>
            <br><br>
            <button type="submit" name="submit">Submit</button>

            <br><br>
 
        </form>
    </section>

</body>
</html>