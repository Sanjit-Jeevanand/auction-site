<?php

function run_automated_proxy_sweep(PDO $pdo): void{
    // find highest active proxy bid for running auctions

    $sql_get_proxies = "SELECT b.auction_id,b.bidder_id,
    MAX(b.proxy_limit)AS highest_proxy_limit,b.increment
    FROM bids b
    JOIN auctions a ON b.auction_id = a.auction_id
    WHERE a.current_status = 'running'
    AND b.is_proxy = 1
    GROUP BY b.auction_id";

    $stmt_get_proxies = $pdo -> query($sql_get_proxies);
    $active_proxies = $stmt_get_proxies->fetchAll(PDO::FETCH_ASSOC);

    // go through all auctions with active proxy

    foreach ($active_proxies as $proxy_data){
        $auction_id = $proxy_data['auction_id'];
        $proxy_bidder_id = $proxy_data['bidder_id'];
        $highest_proxy_limit = (float)$proxy_data['highest_proxy_limit'];
        $increment = (float)$proxy_data['increment'];

        // get highest published bid for an auction and call process_proxy_bids function
        $stmt_highest_bid = $pdo->prepare("SELECT bidder_id,amount
        FROM bids WHERE auction_id=? 
        ORDER BY amount DESC, bid_time DESC LIMIT 1");

        $stmt_highest_bid->execute([$auction_id]);
        $current_top_bid = $stmt_highest_bid->fetch(PDO::FETCH_ASSOC);

        if($current_top_bid && (int)$current_top_bid['bidder_id']!==$proxy_bidder_id){
            process_proxy_bids($pdo,$auction_id,
            (int)$current_top_bid['bidder_id'],
            (float)$current_top_bid['amount'],
            $highest_proxy_limit,
            $increment);
        }

    }
}









?>