<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

$current_seller_id = current_user_id(); 

if (!$current_seller_id) {
    echo "<p>Please log in to view your listings.</p>";
    exit;
}
?>
<style>
.item-list-container {
  margin: 40px auto;
  max-width: 1200px;
  padding: 20px;
  font-family: "Segoe UI", sans-serif;
}

.item-list-container h2 {
  text-align: center;
  margin-bottom: 25px;
  color: #333;
}

.item-list-container .btn-success {
  display: inline-block;
  margin-bottom: 20px;
  padding: 10px 16px;
  background-color: #198754;
  color: white;
  border-radius: 6px;
  text-decoration: none;
}

.items-grid {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 25px;
}

.item-card {
  width: 300px;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.item-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.item-image img {
  width: 100%;
  height: 200px;
  object-fit: cover;
}

.item-details {
  padding: 15px;
}

.item-details p {
  margin: 6px 0;
  color: #444;
}

.item-actions {
  text-align: center;
  margin-top: 10px;
}

.item-actions .btn-success {
  background: #198754;
  border: none;
  color: #fff;
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
}

.item-actions .btn-success:hover {
  background: #157347;
}
</style>


<html>
<div class="item-list-container">
    <h2>Your Listings (Seller ID: <?php echo htmlspecialchars($current_seller_id); ?>)</h2>

    <p>
    <a href="create_item.php" class="btn btn-success">
        Add new item
    </a>
    </p>
    <?php

    $sql_get_items = "SELECT i.item_id,i.title,i.description,i.`condition`, i.created_at,ii.url as image_url,c.name as category_name
    FROM items i
    LEFT JOIN item_images ii ON i.item_id = ii.item_id AND display_order = 1
    LEFT JOIN categories c ON i.category_id = c.category_id
    WHERE i.seller_id = :seller_id
    ";

    $stmt_get_items = $pdo -> prepare($sql_get_items);
    $stmt_get_items -> execute (['seller_id' => $current_seller_id]);
    $items = $stmt_get_items->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    
    <?php if (count($items) > 0): ?>
      <div class="items-grid">
        <?php foreach ($items as $item): ?>

            <div class="item-card">
                
                <div class="item-image">
                    <?php if ($item['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>" 
                             style="width: 200px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <img src="path/to/placeholder.jpg" 
                             alt="No Image Available" 
                             style="width: 200px; height: 150px; object-fit: cover;">
                    <?php endif; ?>
                </div>

                <div class="item-details">
                    <p class="description">
                        <?php echo htmlspecialchars(substr($item['description'], 0, 100)) . '...'; ?>
                         <p class="category">Category: <strong><?php echo htmlspecialchars($item['category_name']); ?></strong></p>
                    </p>
                    <p class="condition">Condition: <strong><?php echo htmlspecialchars($item['condition']); ?></strong></p>
                    <p class="date">Posted: <?php echo date('M j, Y', strtotime($item['created_at'])); ?></p>

                    <div class="item-actions">
                        <form action="set_auction_session.php" method="POST" style="display:inline;">
                            <input type="hidden" name="item_id_to_auction" value="<?php echo $item['item_id']; ?>">
                            <input type="hidden" name="item_name_to_auction" value="<?php echo $item['image_url']; ?>">
                            <button type="submit" class="btn btn-success">
                                Auction Item
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <hr>
        <?php endforeach; ?>
      </div> <!-- end of items-grid -->  
    <?php else: ?>
        <p>No items are currently listed for sale.</p>
    <?php endif; ?>
</div>
</html>