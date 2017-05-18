<?php
use Tygh\Registry;
use Tygh\Languages\Languages;
use Tygh\BlockManager\Block;
use Tygh\Tools\SecurityHelper;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_get_user_params( $login, $password ) {
    return [
        'user_login' => $login,
        'password' => $password,
//        'security_hash' => 'a1d251f459bcad9a6adab92ee9b6daf4',
//        'dispatch' => 'auth.login'
    ];
}

function fn_answer( $array ) {
    header('Content-type: application/json;charset=utf-8');
    echo json_encode( $array );
    exit;
}

function fn_set_user_device( $id, $device_token, $os_type ) {
    $user_data = array(
        'user_id' => $id,
        'device_token' => $device_token,
        'os_type' => $os_type,
    );
    $newDevice = db_query('INSERT INTO ?:users_devices_module_admin ?e', $user_data);
}

function fn_get_users_devices( $id ) {
    return db_get_array("SELECT * FROM ?:users_devices_module_admin WHERE user_id = ?i", $id);
}

function fn_get_user_token( $id ) {
    $token = db_get_fields("SELECT token FROM ?:users_module_admin WHERE user_id = ?i", $id);
    if ( empty($token) || !isset($token) ) {
        $token = md5(mt_rand());
        fn_set_user_token($id, $token);
    }
    return $token;
}

function fn_set_user_token( $id, $token ) {
    $user_data = array(
        'user_id' => $id,
        'token' => $token,
    );
    $newToken = db_query("INSERT INTO ?:users_module_admin ?e", $user_data);
}

function fn_get_all_tokens ( ) {
    return db_get_array("SELECT * FROM ?:users_module_admin WHERE 1");
}

function fn_get_client_info( $client_id ) {
    $userInfo = fn_get_user_info( $client_id );
    $response['client_id'] = $userInfo['user_id'];
    $response['fio'] = $userInfo['firstname'];
    if ( !empty($userInfo['firstname']) && !is_null($userInfo['firstname']) ) {
        $response['fio'] = $userInfo['firstname'];
    }

    if ( !empty($userInfo['lastname']) && !is_null($userInfo['lastname']) ) {
        $response['fio'] .= ' '. $userInfo['lastname'];
    }

    $response['total'] = fn_get_total_sum_orders($client_id);
    $response['quantity'] = fn_get_number_sales_orders($client_id);
    if ( !empty($userInfo['email']) && !is_null($userInfo['email']) ) {
        $response['email'] = $userInfo['email'];
    }

    if ( !empty($userInfo['phone']) && !is_null($userInfo['phone']) ) {
        $response['phone'] = $userInfo['phone'];
    } else {
        $response['phone'] = $userInfo['b_phone'];
    }

    $response['currency_code'] = "".fn_get_currencies_store();
    $response['cancelled'] = fn_get_canceled_orders($client_id);
    $response['completed'] = fn_get_completed_orders($client_id);
    return $response;
}

function fn_get_number_sales_orders( $clien_id ) {
    $count = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i", $clien_id);
    return count($count);
}

function fn_get_total_sum_orders( $clien_id ) {
    $summTotal = 0;
    $total = db_get_array("SELECT total FROM ?:orders WHERE user_id = ?i", $clien_id);
    if ( count($total) > 0 ) {
        foreach ($total as $item) {
            $summTotal += $item['total'];
        }
    }
    return $summTotal;
}

function fn_get_currencies_store() {
    $currency_code = db_get_fields("SELECT currency_code FROM ?:currencies WHERE is_primary = ?s ", 'Y');
    return implode($currency_code);
}

function fn_get_canceled_orders( $client_id ) {
    $count = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i AND status = ?s", $client_id, 'D');
    return count($count);
}

function fn_get_completed_orders( $client_id ) {
    $count = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i AND status = ?s", $client_id, 'C');
    return count($count);
}

function fn_get_client_orders( $clientOrders ) {
    $responseArray = [];
    foreach ( $clientOrders as $key => $order ) {
        $responseArray[$key]['order_id'] = $order['order_id'];
        $responseArray[$key]['order_number'] = $order['order_id'];
        $responseArray[$key]['status'] = fn_get_name_order_status($order['status']);
        $responseArray[$key]['currency_code'] = fn_get_currencies_store();
        $responseArray[$key]['total'] = $order['total'];
        $responseArray[$key]['date_added'] = gmdate("Y-m-d H:i:s", $order['timestamp']);;
    }
    return $responseArray;
}

function fn_get_name_order_status( $type_status ) {
    $statusId = fn_get_simple_statuses();
    return $statusId[$type_status];
}

function fn_get_order_history( $order_id ) {
    $order = fn_get_order_by_id( $order_id )[0];
    $statusInfo = fn_get_status_order_by_code($order['status']);
    $arrayAnswer['orders']['name'] = $statusInfo['name'];
    $arrayAnswer['orders']['order_status_id'] = $statusInfo['order_status_id'];
    $arrayAnswer['orders']['date_added'] = date('Y-m-d H:m:s', $order['timestamp']);
    $arrayAnswer['statuses'] = fn_get_status_all_order();
    return $arrayAnswer;
}

function fn_delete_user_device_token( $old_token ) {
    $tokens = db_get_array("SELECT * FROM ?:users_devices_module_admin WHERE device_token = ?s", $old_token);
    $answer = false;
    if ( count($tokens) !== 0 ) {
        $tokens = db_query("DELETE FROM ?:users_devices_module_admin WHERE device_token = ?s", $old_token);
        $answer = count($tokens);
    }
    return $answer;
}

function fn_update_user_device_token( $old_token, $new_token ) {
    $tokens = db_get_array("SELECT * FROM ?:users_devices_module_admin WHERE device_token = ?s", $old_token);
    $answer = false;
    $data = [
        'device_token' => $new_token
    ];
    if ( count($tokens) !== 0 ) {
        $tokens = db_query("UPDATE ?:users_devices_module_admin SET ?u WHERE device_token = ?s", $data, $old_token);
        $answer = count($tokens);
    }
    return $answer;
}

function fn_get_payment_and_shipping_by_id( $order_id ) {
    $order = end(db_get_array("SELECT shipping_ids, payment_id, b_address, b_city, b_state, b_country  FROM ?:orders WHERE order_id = ?i", $order_id));
    $data = [];
    if ( $order !== false ) {
        $data['error'] = null;
        $data['answer']['payment_method'] = fn_get_payment_method_by_id($order['payment_id']);
        $data['answer']['shipping_method'] = fn_get_shipping_method_by_id($order['shipping_ids']);
        $data['answer']['shipping_address'] = $order['b_address'].", ".$order['b_city'].", ".$order['b_state'].", ".fn_get_country_by_code($order['b_country']);
    } else {
        $data['error'] = 'error';
    }
    return $data;
}

function fn_get_country_by_code ( $countryCode ) {
    $countryName = db_get_fields("SELECT country FROM ?:country_descriptions WHERE code = ?s", $countryCode);
    return implode($countryName);
}

function fn_get_payment_method_by_id( $paymentId ) {
    $paymentMethod = db_get_fields("SELECT payment FROM ?:payment_descriptions WHERE payment_id = ?i", $paymentId);
    return implode($paymentMethod);
}

function fn_get_shipping_method_by_id( $shippingId ) {
    $shippingMethod = db_get_fields("SELECT shipping FROM ?:shipping_descriptions WHERE shipping_id = ?i", $shippingId);
    return implode($shippingMethod);
}

function fn_get_order_by_id( $order_id ) {
    $order = db_get_array("SELECT * FROM ?:orders WHERE order_id = ?i", $order_id);
    return $order;
}

function fn_set_new_address_for_order_by_id( $order_id, $newAddress ) {
    $data = [
        'b_address' => $newAddress,
        's_address' => $newAddress
    ];
    db_query("UPDATE ?:orders SET ?u WHERE order_id = ?i", $data, $order_id);
}

function fn_set_new_city_for_order_by_id( $order_id, $newCity ) {
    $data = [
        'b_city' => $newCity,
        's_city' => $newCity
    ];
    db_query("UPDATE ?:orders SET ?u WHERE order_id = ?i", $data, $order_id);
}

function fn_get_clients( $data = [] ) {
    $usersWithAllFields = [];
    if ( isset($data['fio']) && !empty($data['fio']) ) {
        $allParams = [];
        $paramsFIO = explode(' ', $data['fio']);

        foreach ($paramsFIO as $key => $value) {
            if ($value == '') {
                unset($paramsFIO[$key]);
            } else {
                $allParams[$key]['firstname'] = $value;
                $allParams[$key]['lastname'] = $value;
            }
        }
        if ( $data['sort'] == 'date_added' ) {
            foreach ($allParams as $key => $value ) {
                $arrayUser[] = db_get_array("SELECT user_id, firstname, lastname FROM ?:users WHERE firstname = ?s OR lastname =?s ORDER BY timestamp DESC", $value['firstname'], $value['lastname']);
            }
        } else {
            foreach ($allParams as $key => $value ) {
                $arrayUser[] = db_get_array("SELECT user_id, firstname, lastname FROM ?:users WHERE firstname = ?s OR lastname =?s", $value['firstname'], $value['lastname']);
            }
        }

        foreach (end($arrayUser) as $key => $item ) {
            $usersWithAllFields[$key]['client_id'] = $item['user_id'];
            $usersWithAllFields[$key]['fio'] = $item['firstname'] ." ". $item['lastname'];
            $usersWithAllFields[$key]['total'] = fn_get_total_sum_orders($item['user_id']);
            $usersWithAllFields[$key]['quantity'] = fn_get_number_sales_orders($item['user_id']);
        }
    }
    switch ( $data['sort'] ) {
        case 'sum':
            $usersWithAllFields = fn_sort_array_by_total($usersWithAllFields);
            break;
        case 'quantity':
            $usersWithAllFields = fn_sort_array_by_quantity($usersWithAllFields);
            break;
        default:

    }
    if ( $data['limit'] > count($usersWithAllFields) ) {
        $data['limit'] = count($usersWithAllFields);
    }
    $count = 0;
    $arrayUsersPagination = [];
    for ( $i = ( $data['page'] * $data['limit'] ) - $data['limit']; $i < ( $data['page'] * $data['limit'] ); $i++ ) {
        $arrayUsersPagination[$count]['client_id'] = $usersWithAllFields[$i]['client_id'];
        $arrayUsersPagination[$count]['fio'] = $usersWithAllFields[$i]['fio'];
        $arrayUsersPagination[$count]['total'] = $usersWithAllFields[$i]['total'];
        $arrayUsersPagination[$count]['currency_code'] = "".fn_get_currencies_store();
        $arrayUsersPagination[$count]['quantity'] = $usersWithAllFields[$i]['quantity'];
        $count++;
    }
    return $arrayUsersPagination;
}

function fn_sort_array_by_quantity( $array ) {
    usort($array, function($a, $b){
        return ($b['quantity'] - $a['quantity']);
    });
    return $array;
}

function fn_sort_array_by_total( $array ) {
    usort($array, function($a, $b){
        return ($b['total'] - $a['total']);
    });
    return $array;
}

function fn_searchPage( array $pagesList, /*int*/ $needPage )
{
    foreach( $pagesList AS $chunk => $pages  ){
        if( in_array($needPage, $pages) ){
            return $chunk;
        }
    }
    return 0;
}

function fn_get_product_info_by_id_order( $order_id ) {
    $arrayProductId = db_get_fields("SELECT product_id FROM ?:order_details WHERE order_id = ?i", $order_id);
    foreach ( $arrayProductId as $key => $item ) {
        $imageId = implode(db_get_fields("SELECT detailed_id FROM ?:images_links WHERE object_type='product' AND type='M' AND object_id =?i", $item));
//        if ( is_null($imageId) ) {
//            $dataAnswer['products'][$key]['image'] = "no_image.gif";
//        } else {
//            $image = db_get_fields("SELECT image_path FROM ?:images WHERE image_id = ?i", $imageId);
//            $dataAnswer['products'][$key]['image'] = implode($image);
//        }
        $dataAnswer['products'][$key]['image'] = fn_get_image($imageId, 'detailed')['image_path'];
        $dataAnswer['products'][$key]['name'] = fn_get_product_name($item);
        $dataAnswer['products'][$key]['model'] = fn_get_product_code_by_id($item, $order_id);
        $dataAnswer['products'][$key]['quantity'] = fn_get_product_amount_in_order($item, $order_id);
        $dataAnswer['products'][$key]['price'] = fn_get_product_price_by_id($item);
        $dataAnswer['products'][$key]['product_id'] = $item;
    }
    $orderInfo = fn_get_order_by_id($order_id);
    $dataAnswer['total_order_price']['total_discount'] = $orderInfo[0]['subtotal_discount'];
    $dataAnswer['total_order_price']['total_price'] = $orderInfo[0]['subtotal'];
    $dataAnswer['total_order_price']['currency_code'] = fn_get_currencies_store();
    $dataAnswer['total_order_price']['shipping_price'] = $orderInfo[0]['shipping_cost'];
    $dataAnswer['total_order_price']['total'] = $orderInfo[0]['total'];
    return $dataAnswer;
}

function fn_get_product_price_by_id( $product_id ) {
    $priceProduct = db_get_fields("SELECT price FROM ?:product_prices WHERE product_id = ?i", $product_id);
    return implode($priceProduct);
}

function fn_get_product_amount_in_order( $product_id, $order_id ) {
    $count = db_get_fields("SELECT amount FROM ?:order_details WHERE product_id = ?i AND order_id = ?i", $product_id, $order_id);
    return implode($count);
}

function fn_get_product_code_by_id( $product_id, $order_id ) {
    $code = db_get_fields("SELECT product_code FROM ?:order_details WHERE product_id = ?i AND order_id = ?i", $product_id, $order_id);
    return implode($code);
}

function fn_get_product_description_by_id( $product_id ) {
    $description = db_get_row("SELECT *  FROM ?:product_descriptions WHERE product_id = ?i", $product_id);
    return $description['full_description'];
}

function fn_get_product_info_by( $product_id ) {
    $arrayAnswer = [];
    $products = db_get_row("SELECT *  FROM ?:products WHERE product_id = ?i", $product_id);
    if ( count($products) > 0 ) {
        $arrayAnswer['product_id'] = $product_id;
        $arrayAnswer['status'] = fn_get_status_id_by_code($products['status']);
        $arrayAnswer['model'] = $products['product_code'];
        $arrayAnswer['name'] = fn_get_product_name($product_id);
        $arrayAnswer['price'] = fn_get_product_price_by_id($product_id);
        $arrayAnswer['currency_code'] = fn_get_currencies_store();
        $arrayAnswer['quantity'] = $products['amount'];
        $arrayAnswer['description'] = fn_get_product_description_by_id($product_id);
        $arrayAnswer['images'] = fn_get_array_images_product_by_id($product_id);
    } else {
        $arrayAnswer = '';
    }
    return $arrayAnswer;
}

function fn_get_array_images_product_by_id( $product_id ) {
    $imagesIds = db_get_array("SELECT detailed_id, type FROM ?:images_links WHERE object_type='product' AND object_id =?i", $product_id);
    $imagesPaths = [];
    $imagesIds = fn_sort_array_by_type($imagesIds);
    foreach ( $imagesIds as $item ) {
        $imagesPaths[] = fn_get_image($item['detailed_id'], 'detailed')['image_path'];
    }
    return $imagesPaths;
}

function fn_get_one_images_product_by_id( $product_id ) {
    $imagesIds = db_get_fields("SELECT detailed_id FROM ?:images_links WHERE object_type='product' AND type = 'M' AND object_id =?i", $product_id);
    $imagesPaths = [];
    foreach ( $imagesIds as $item ) {
        $imagesPaths = fn_get_image($item, 'detailed')['image_path'];
    }
    return $imagesPaths;
}

function fn_get_product_list( $page, $limit, $name = '' ) {
    if ( isset($name) && !empty($name) ) {
        $allProducts = db_get_array("SELECT * FROM ?:products, ?:product_descriptions WHERE ?:products.product_id = ?:product_descriptions.product_id AND product LIKE ?s LIMIT ?i,?i ", '%' . $name . '%', $page, $limit);
    } else {
        $allProducts = db_get_array("SELECT * FROM ?:products, ?:product_descriptions WHERE ?:products.product_id = ?:product_descriptions.product_id LIMIT ?i,?i ", $page, $limit);
    }
    $arrayResonse = [];
    if ( count($allProducts) > 0 ) {
        foreach ( $allProducts as $key => $value ) {
            $arrayResonse[$key]['product_id'] = $value['product_id'];
            $arrayResonse[$key]['model'] = $value['product_code'];
            $arrayResonse[$key]['name'] = $value['product'];
            $arrayResonse[$key]['price'] = fn_get_product_price_by_id($value['product_id']);
            $arrayResonse[$key]['currency_code'] = "".fn_get_currencies_store();
            $arrayResonse[$key]['quantity'] = $value['amount'];
            $arrayResonse[$key]['image'] = fn_get_one_images_product_by_id($value['product_id']);
        }
    }
    return $arrayResonse;
}

function fn_get_total_customers( $data = [] ) {
    if ( isset($data['filter']) && !empty($data['filter']) ) {
        switch ( $data['filter'] ) {
            case 'day':
                $timestamp = time();
                $users = db_get_array("SELECT * FROM ?:users WHERE from_unixtime(timestamp,'%Y %D %M') = from_unixtime(".$timestamp.",'%Y %D %M')");
                break;
            case 'week':
                $date_w = strtotime('-' . date('w') . ' days');
                $users = db_get_array("SELECT * FROM ?:users WHERE timestamp >= ".$date_w);
                break;
            case 'month':
                $date_m = strtotime(date('Y') . '-' . date('m') . '-1');
                $users = db_get_array("SELECT * FROM ?:users WHERE timestamp >= ".$date_m);
                break;
            case 'year':
                $date_y = strtotime(date('Y'.'-1'));
                $users = db_get_array("SELECT * FROM ?:users WHERE timestamp >= ".$date_y);
                break;
            default:
                return false;
        }
    } else {
        $users = implode(db_get_fields("SELECT count(*) FROM ?:users WHERE 1"));
    }
    return $users;
}

function fn_get_total_orders( $data = [] ) {
    if ( isset($data['filter']) && !empty($data['filter']) ) {
        switch ( $data['filter'] ) {
            case 'day':
                $timestamp = time();
                $orders = db_get_array("SELECT * FROM ?:orders WHERE from_unixtime(timestamp,'%Y %D %M') = from_unixtime(".$timestamp.",'%Y %D %M')");
                break;
            case 'week':
                $date_w = strtotime('-' . date('w') . ' days');
                $orders = db_get_array("SELECT * FROM ?:orders WHERE timestamp >= ".$date_w);
                break;
            case 'month':
                $date_m = strtotime(date('Y') . '-' . date('m') . '-1');
                $orders = db_get_array("SELECT * FROM ?:orders WHERE timestamp >= ".$date_m);
                break;
            case 'year':
                $date_y = strtotime(date('Y'.'-1'));
                $orders = db_get_array("SELECT * FROM ?:orders WHERE timestamp >= ".$date_y);
                break;
            default:
                return false;
        }
    } else {
        $orders = implode(db_get_fields("SELECT count(*) FROM ?:orders WHERE 1"));
    }
    return $orders;
}

function get_total_sales( $data = [] ) {
    $total = 0;
    $orders = db_get_array("SELECT * FROM ?:orders WHERE 1");
    if (!empty($data['this_year'])) {
        $date_y = strtotime(date('Y'.'-1'));
        $orders = db_get_array("SELECT * FROM ?:orders WHERE timestamp >= ".$date_y);
    }
    foreach ( $orders as $value ) {
        $total += $value['total'];
    }
    return $total;
}

function fn_get_status_order_by_code( $statusCode ) {
    $status = fn_get_statuses();
    $status = $status[$statusCode];
    $langCode = $status['lang_code'];
    $answer['name'] = $status['description'];
    $answer['order_status_id'] = $status['status_id'];
    $answer['language_id'] = fn_get_languages()[$langCode]['lang_id'];
    return $answer;
}

function fn_get_status_id_castom( $statusId ) {
    $statuses = fn_get_statuses();
    $status = false;
    foreach ( $statuses as $key => $value ) {
        if ( $value['status_id'] == $statusId ) {
            $status = $value['status'];
        }
    }
    return $status;
}

function fn_get_max_order_price() {
    $maxOrder = implode(db_get_fields("SELECT MAX(total) FROM ?:orders WHERE status <> 'D'"));
    return $maxOrder;
}

function fn_get_orders_castom( $data = [] ) {
    $query = '';
    if ( isset($data['filter']) ) {
        if (isset($data['filter']['order_status_id']) && !empty($data['filter']['order_status_id']) && !is_null($data['filter']['order_status_id']) && $data['filter']['order_status_id'] !== '0' ) {
            $statusCode = fn_get_status_code_by_id( $data['filter']['order_status_id'] );
            $query .= 'WHERE status = '.$statusCode;
        } else {
            $query .= "WHERE status <> 'D'";
        }
        if (isset($data['filter']['fio']) && $data['filter']['fio'] !== '' && $data['filter']['fio'] !== 'null' && $data['filter']['fio'] !== '0' && !ctype_digit($data['filter']['fio']) && intval($data['filter']['fio']) == 0) {
            $query .= ' AND';
            $allParams = [];
            $paramsFIO = explode(' ', $data['filter']['fio']);
            foreach ($paramsFIO as $key => $value) {
                if ($value == '') {
                    unset($paramsFIO[$key]);
                } else {
                    $allParams[$key]['firstname'] = $value;
                    $allParams[$key]['lastname'] = $value;
                }
            }
            $query .= " ( b_firstname LIKE '%" . $allParams[0]['firstname'] . "%' OR b_lastname LIKE '%" . $allParams[0]['lastname'] . "%'";
            foreach ($allParams as $key => $param) {
                if ($key != $param[0]) {
                    $query .= " OR b_firstname LIKE '%" . $param['firstname'] . "%' OR b_lastname LIKE '%" . $param['lastname'] . "%'";
                };
            }
            $query .= " ) ";
        }
        if ($data['filter']['min_price'] == 0) {
            $data['filter']['min_price'] = 1;
        }

        if (isset($data['filter']['min_price']) && isset($data['filter']['max_price']) && !empty($data['filter']['max_price']) && $data['filter']['max_price'] != 0 && $data['filter']['min_price'] !== 0 && !empty($data['filter']['min_price']) ) {
            $query .= ' AND';
            $query .= " total > " . $data['filter']['min_price'] . " AND total <= " . $data['filter']['max_price'];
        }

        if (isset($data['filter']['date_min']) && !empty($data['filter']['date_min']) ) {
            $date_min = strtotime($data['filter']['date_min']);
            $query .= " AND timestamp > " . $date_min;
        }

        if (isset($data['filter']['date_max']) && !empty($data['filter']['date_max']) ) {
            $date_min = strtotime($data['filter']['date_max']);
            $query .= " AND timestamp < " . $date_min;
        }
    } else {
        $query = "WHERE status <> 'D'";
    }
    $query .= " LIMIT " . (int)$data['limit'] . " OFFSET " . (int)$data['page'];
    $listOrders = db_get_array("SELECT * FROM ?:orders ". $query);
    $sum = 0;
    $quantity = 0;
    foreach ($listOrders as $value) {
        $sum = $sum + $value['total'];
        $quantity++;
    }
    $listOrders['totalsumm'] = $sum;
    $listOrders['quantity'] = $quantity;
    return $listOrders;
}

function fn_get_status_code_by_id( $statusId ) {
    $status = fn_get_statuses();
    $statusCode = '';
    foreach ( $status as $key => $value ) {
        if ( $value['status_id'] == $statusId ) {
            $statusCode = $value['status'];
        }
    }
    return $statusCode;
}

function fn_get_status_all_order() {
    $statuses = fn_get_statuses();
    $count = 0;
    foreach ($statuses as $key => $value ) {
        $langCode = $value['lang_code'];
        $answer[$count]['name'] = $value['description'];
        $answer[$count]['order_status_id'] = $value['status_id'];
        $answer[$count]['language_id'] = fn_get_languages()[$langCode]['lang_id'];
        $count++;
    }
    return $answer;
}

function fn_set_quantity_for_product( $productId, $quantity ) {
    $data = [
        'amount' => $quantity
    ];
    return boolval(db_query("UPDATE ?:products SET ?u WHERE product_id = ?i", $data, $productId));
}

function fn_update_product_name( $productId, $name ) {
    $data = [
        'product' => $name
    ];
    return boolval(db_query("UPDATE ?:product_descriptions SET ?u WHERE product_id = ?i", $data, $productId));
}

function fn_update_product_new( $data = [] ) {
    $dataProducts = [];
    $dataDescription = [];
    if ( isset($data['quantity']) && !empty($data['quantity']) ) {
        $dataProducts['amount'] = $data['quantity'];
    }
    if ( isset($data['model']) && !empty($data['model']) ) {
        $dataProducts['product_code'] = $data['model'];
    }
    if ( isset($data['status']) && !empty($data['status']) ) {
        $dataProducts['status'] = fn_get_status_id_castom($data['status']);
    }
    if ( isset($data['name']) && !empty($data['name']) ) {
        $dataDescription['product'] = $data['name'];
    }
    if ( isset($data['description']) && !empty($data['description']) ) {
        $dataDescription['full_description'] = $data['description'];
    }
    if ( isset($data['images']) && !empty($data['images']) ) {

    }
    if ( !empty($dataProducts) )
        db_query("UPDATE ?:products SET ?u WHERE product_id = ?i", $dataProducts, $data['product_id']);
    if ( !empty($dataDescription) )
        db_query("UPDATE ?:product_descriptions SET ?u WHERE product_id = ?i", $dataDescription, $data['product_id']);
    return true;
}

function fn_sort_array_by_type( $array ) {
    usort($array, function($a, $b){
        return ($b['type'] - $a['type']);
    });
    return $array;
}

function fn_get_status_id_by_code( $codeStatus ) {
    $statuses = fn_get_statuses();
    $status = false;
    foreach ( $statuses as $key => $value ) {
        if ( $value['status'] == $codeStatus ) {
            $status = $value['status_id'];
        }
    }
    return $status;
}