
<?php

function process_proxy_bids(PDO $pdo, int $auction_id, int $new_bidder_id, float $new_amount, ?float $new_proxy_limit, ?float $new_increment): void{
    $sql_max_bid = "SELECT b.bidder_id,b.amount as published_amount, max(b.proxy_limit) as proxy_limit,b.increment 
    FROM bids b where b.auction_id = :auction_id AND b.bidder_id != :new_bidder_id
    ORDER BY b.amount DESC,b.bid_time DESC LIMIT 1 ";

    $stmt_max_bid = $pdo -> prepare($sql_max_bid);

    $stmt_max_bid-> execute(['auction_id' => $auction_id,
                                    'new_bidder_id' =>$new_bidder_id]);

    $existing_top_bid = $stmt_max_bid->fetch(PDO::FETCH_ASSOC);

    if (!$existing_top_bid){
        return;
    }
    
    $existing_bidder_id   = (int)$existing_top_bid['bidder_id'];
    // keep proxy_limit as NULL if there is no proxy set
    $existing_proxy_limit = $existing_top_bid['proxy_limit'] !== null
        ? (float)$existing_top_bid['proxy_limit']
        : null;
    $existing_increment   = (float)$existing_top_bid['increment'];
    $current_highest_bid  = $new_amount;

    // 🚫 If the existing top bidder has no proxy limit set, there is
    // nothing to auto-bid – just return and let the new bid stand.
    if ($existing_proxy_limit === null) {
        return;
    }

    // amount needed for the proxy bidder to outbid the new bid
    $required_outbid_amount = $current_highest_bid + $existing_increment;

    if ($existing_proxy_limit >= $required_outbid_amount) {

        // the amount required for proxy to outbid
        $auto_bid_amount = $required_outbid_amount;

        //making sure that we dont exceed the proxy limit
        $final_bid_amount = min($auto_bid_amount,$existing_proxy_limit);

        $sql_auto_bid = "INSERT INTO bids (auction_id,bidder_id,amount,bid_time,
        is_proxy,proxy_limit,increment) VALUES (:auction_id,:bidder_id,:amount,NOW(),1,
        :proxy_limit,:increment)";

        $stmt_auto_bid = $pdo->prepare($sql_auto_bid);

        $stmt_auto_bid -> execute(['auction_id' => $auction_id,
        'bidder_id' => $existing_bidder_id,
        'amount' => $final_bid_amount,
        'proxy_limit'=>$existing_proxy_limit,
        'increment' => $existing_increment]);
    }
    else {
        add_notification($existing_bidder_id,$auction_id,'outbid','Your proxy bid limit has been reached and you were outbid');
    }
}
?>