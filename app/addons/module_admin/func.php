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
    $response = [];
    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token( $_REQUEST['token'] );
        $company = fn_get_company_id_user_type_by_token( $userId );

        if ( $company['company_id'] == '0' && $company['user_type'] == 'A' ) {
            $userInfo = fn_get_user_info( $client_id );
        } else {
            $userInfo = fn_get_user_info( $client_id );
            $orderCompanyId = db_get_field("SELECT company_id FROM ?:orders WHERE user_id = ?i and company_id = ?i", $client_id, $company['company_id']);
            if ( empty($orderCompanyId) ) {
                return [];
            }
        }
    } else {
        $userInfo = fn_get_user_info( $client_id );
    }
    $response['client_id'] = $userInfo['user_id'];
    $response['company_id'] = $userInfo['company_id'];
    $response['fio'] = $userInfo['firstname'];
    if ( !empty($userInfo['firstname']) && !is_null($userInfo['firstname']) ) {
        $response['fio'] = $userInfo['firstname'];
    }

    if ( !empty($userInfo['lastname']) && !is_null($userInfo['lastname']) ) {
        $response['fio'] .= ' '. $userInfo['lastname'];
    }

    $response['total'] = "".fn_get_total_sum_orders($client_id);
    $response['quantity'] = "".fn_get_number_sales_orders($client_id);
    if ( !empty($userInfo['email']) && !is_null($userInfo['email']) ) {
        $response['email'] = $userInfo['email'];
    }

    if ( !empty($userInfo['phone']) && !is_null($userInfo['phone']) ) {
        $response['telephone'] = $userInfo['phone'];
    } else {
        $response['telephone'] = $userInfo['b_phone'];
    }

    $response['currency_code'] = "".fn_get_currencies_store();
    $response['cancelled'] = "".fn_get_canceled_orders($client_id);
    $response['completed'] = "".fn_get_completed_orders($client_id);

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
    if ( $type_status == 'N' ) {
        return '';
    } else {
        $statusId = fn_get_simple_statuses();
        return isset($statusId[$type_status]) ? $statusId[$type_status] : null;
    }
}

function fn_get_order_history( $order_id ){
    $order = [];
    if (Registry::get('addons.module-admin.is_multivendor') == 'Y') {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ($company['user_type'] == 'A' && $company['company_id'] == '0') {
            $order = db_get_array("SELECT * FROM ?:orders WHERE order_id = ?i", $order_id)[0];
        } else {
            $order = db_get_array("SELECT * FROM ?:orders WHERE order_id = ?i and company_id = ?i", $order_id, $company['company_id'])[0];
        }
        $statusInfo = fn_get_status_order_by_code($order['status']);
    } else {
        $order = fn_get_order_by_id($order_id)[0];
        $statusInfo = fn_get_status_order_by_code($order['status']);
    }

    return get_fields_for_order_history( $order, $statusInfo );
}

function get_fields_for_order_history( $order, $statusInfo ) {
    $arrayOrders = [];
    if ( is_null($order) ) {
        $arrayAnswer['orders'][] = [];
        $arrayAnswer['statuses'] = fn_get_status_all_order();
        return $arrayAnswer;
    }
    $arrayOrders['name'] = $statusInfo['name'];
    $arrayOrders['order_status_id'] = $statusInfo['order_status_id'];
    $arrayOrders['date_added'] = date('Y-m-d H:m:s', $order['timestamp']);
    $arrayAnswer['orders'][] = $arrayOrders;
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

    $query = "SELECT shipping_ids, payment_id, b_address, b_city, b_state, b_country  FROM ?:orders WHERE order_id = ?i";
    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $query .= " and company_id = ".  $company['company_id'];
        }
    }
    $order = end(db_get_array( $query , $order_id));
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
    $query = "SELECT * FROM ?:orders WHERE order_id = ?i";
    if (( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' )) && isset($_REQUEST['token'])) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $query .= " and company_id = ". $company['company_id'];
        }
    }
    $order = db_get_array( $query , $order_id);
    return $order;
}

function fn_set_new_address_for_order_by_id( $order_id, $newAddress ) {
    $data = [
        'b_address' => $newAddress,
        's_address' => $newAddress
    ];
    $query = "UPDATE ?:orders SET ?u WHERE order_id = ?i";
    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $query .= " and company_id = ".  $company['company_id'];
        }
    }

    return (bool)db_query( $query , $data, $order_id);
}

function fn_set_new_city_for_order_by_id( $order_id, $newCity ) {
    $data = [
        'b_city' => $newCity,
        's_city' => $newCity
    ];
    $query = "UPDATE ?:orders SET ?u WHERE order_id = ?i";
    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $query .= " and company_id = ".  $company['company_id'];
        }
    }
    return (bool)db_query( $query , $data, $order_id);
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

        $query = "SELECT user_id, firstname, lastname FROM ?:users WHERE  ";
        if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
            $userId = fn_get_user_id_by_token($_REQUEST['token']);
            $company = fn_get_company_id_user_type_by_token($userId);
            if ( $company['user_type'] != 'A' ) {
                $getIdUsers = db_get_fields( "SELECT DISTINCT(user_id) FROM ?:orders WHERE company_id = ?i", $company['company_id'] );
                $query .= " user_id in (".implode(',', $getIdUsers).") and (firstname = ?s OR lastname =?s)";
            } else $query .= "firstname = ?s OR lastname = ?s";
        } else {
            $query .= "firstname = ?s OR lastname = ?s";
        }
        if ( $data['sort'] == 'date_added' ) {
            $query .= " ORDER BY timestamp DESC";
            foreach ($allParams as $key => $value ) {
                $arrayUser[] = db_get_array( $query , $value['firstname'], $value['lastname']);
            }
        } else {

            foreach ($allParams as $key => $value ) {
                $arrayUser[] = db_get_array($query, $value['firstname'], $value['lastname']);
            }
        }

        foreach (end($arrayUser) as $key => $item ) {
            $usersWithAllFields[$key]['client_id'] = $item['user_id'];
            $usersWithAllFields[$key]['fio'] = $item['firstname'] ." ". $item['lastname'];
            $usersWithAllFields[$key]['total'] = fn_get_total_sum_orders($item['user_id']);
            $usersWithAllFields[$key]['quantity'] = fn_get_number_sales_orders($item['user_id']);
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

    } else {
        $query = "SELECT user_id, firstname, lastname FROM ?:users";
        if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
            $userId = fn_get_user_id_by_token($_REQUEST['token']);
            $company = fn_get_company_id_user_type_by_token($userId);
            if ( $company['user_type'] != 'A' ) {
                $getIdUsers = db_get_fields( "SELECT DISTINCT(user_id) FROM ?:orders WHERE company_id = ?i", $company['company_id'] );
                if ( empty($getIdUsers) ) {
                    return [];
                } else $query .= " WHERE user_id in (".implode(',', $getIdUsers).")";
            }
        }


        $arrayUser[] = db_get_array( $query );
        $counter = 0;

        foreach (end($arrayUser) as $key => $item ) {
            $usersWithAllFields[$counter]['client_id'] = $item['user_id'];
            $usersWithAllFields[$counter]['fio'] = $item['firstname'] ." ". $item['lastname'];
            $usersWithAllFields[$counter]['total'] = fn_get_total_sum_orders($item['user_id']);
            $usersWithAllFields[$counter]['quantity'] = fn_get_number_sales_orders($item['user_id']);
            $counter++;
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
            $arrayUsersPagination[$count]['client_id'] = $usersWithAllFields[$count]['client_id'];
            $arrayUsersPagination[$count]['fio'] = $usersWithAllFields[$count]['fio'];
            $arrayUsersPagination[$count]['total'] = "".$usersWithAllFields[$count]['total'];
            $arrayUsersPagination[$count]['currency_code'] = "".fn_get_currencies_store();
            $arrayUsersPagination[$count]['quantity'] = "".$usersWithAllFields[$count]['quantity'];
            $count++;
        }
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
    $arrayProductId = db_get_fields( "SELECT product_id FROM ?:order_details WHERE order_id = ?i" , $order_id);
    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $productId = db_get_field("SELECT order_id FROM ?:orders WHERE order_id = ?i and company_id = ?i" , $order_id, $company['company_id']);

            if ( empty($productId) ) {
                $productId = db_get_field("SELECT order_id FROM ?:orders WHERE parent_order_id = ?i and company_id = ?i" , $order_id, $company['company_id']);
            }
            $arrayProductId = db_get_fields( "SELECT product_id FROM ?:order_details WHERE order_id = ?i" , $productId);

        }
    }

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
    $query = "SELECT *  FROM ?:products WHERE product_id = ?i";
    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $query .= " and company_id = ". $company['company_id'];
        }
    }

    $arrayAnswer = [];
    $products = db_get_row( $query , $product_id);
    if ( count($products) > 0 ) {
        $arrayAnswer['product_id'] = $product_id;
        $arrayAnswer['status_name'] = "".fn_get_status_name_by_code($products['status']);
        $arrayAnswer['code'] = "".$products['product_code'];
        $arrayAnswer['name'] = "".fn_get_product_name($product_id);
        $arrayAnswer['price'] = "".fn_get_product_price_by_id($product_id);
        $arrayAnswer['currency_code'] = "".fn_get_currencies_store();
        $arrayAnswer['quantity'] = "".$products['amount'];
        $arrayAnswer['categories'] = fn_get_category_name_by_product_id_all( $product_id );
        $arrayAnswer['description'] = "".fn_get_product_description_by_id($product_id);
        $arrayAnswer['images'] = fn_get_array_images_product_by_id($product_id);
        $arrayAnswer['options'] = fn_get_product_options_by_id($product_id);
    } else {
        $arrayAnswer = '';
    }
    return $arrayAnswer;
}

function fn_get_status_name_by_code( $status ) {
    $statusName  = fn_get_status_id_by_code($status);
    if ( $status == 'A' ) {
        $statusName = 'Enabled';
    }
    if ( $status == 'D' ) {
        $statusName = 'Disabled';
    }
    return $statusName;
}

function fn_sort_array_by_image_id( $array ) {
    usort($array, function($a, $b){
        return ($a['image_id'] - $b['image_id']);
    });
    return $array;
}

function fn_get_array_images_product_by_id( $product_id ) {
    $imagesIds = db_get_array("SELECT detailed_id, type FROM ?:images_links WHERE object_type='product' AND object_id =?i", $product_id);
    $imagesPaths = [];
    $imagesIds = fn_sort_array_by_type($imagesIds);

    $counter = 0;
    foreach ( $imagesIds as $key => $item ) {
        if ( $item['type'] == 'M' ) {
            $path = fn_get_image($item['detailed_id'], 'detailed')['image_path'];
            $imagesPaths[$counter]['image_id'] = '-1';
            $imagesPaths[$counter]['image'] = $path;
            $counter++;
            unset($imagesIds[$key]);
        }
    }

//    if ( empty($imagesPaths) || is_null($imagesPaths) ) {
//        $imagesPaths[0]['image_id'] = '-1';
//        $imagesPaths[0]['image'] = '';
//        $counter++;
//    }

    foreach ( $imagesIds as $key => $item ) {
        $path = fn_get_image($item['detailed_id'], 'detailed')['image_path'];
        $idImage = fn_get_image($item['detailed_id'], 'detailed')['image_id'];
        $imagesPaths[$counter]['image_id'] = $idImage;
        $imagesPaths[$counter]['image'] = $path;
        $counter++;
    }

    if ( empty($imagesPaths) || is_null($imagesPaths) ) {
        $imagesPaths[0]['image_id'] = '-1';
        $imagesPaths[0]['image'] = '';
    }
    return fn_sort_array_by_image_id($imagesPaths);
}



function fn_get_one_images_product_by_id( $product_id ) {
    $imagesIds = db_get_fields("SELECT detailed_id FROM ?:images_links WHERE object_type='product' AND type = 'M' AND object_id =?i", $product_id);
    $imagesPaths = [];
    foreach ( $imagesIds as $item ) {
        $imagesPaths = fn_get_image($item, 'detailed')['image_path'];
    }
    if ( empty($imagesIds) ) {
        return '';
    } else {
        return $imagesPaths;
    }
}

function fn_get_product_list( $page, $limit, $name = '' ) {
    $query = "SELECT * FROM ?:products, ?:product_descriptions WHERE ?:products.product_id = ?:product_descriptions.product_id";
    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $query .= " and company_id = ". $company['company_id'];
        }
    }

    if ( isset($name) && !empty($name) ) {
        $allProducts = db_get_array( $query ." AND product LIKE ?s LIMIT ?i,?i ", '%' . $name . '%', $page, $limit);
    } else {
//        fn_print($limit);
        $allProducts = db_get_array( $query ." LIMIT ?i,?i ", $page, $limit);
    }
    $arrayResonse = [];
    if ( count($allProducts) > 0 ) {
        foreach ( $allProducts as $key => $value ) {
            $arrayResonse[$key]['product_id'] = $value['product_id'];
            $arrayResonse[$key]['code'] = $value['product_code'];
            $arrayResonse[$key]['name'] = $value['product'];
            $arrayResonse[$key]['price'] = fn_get_product_price_by_id($value['product_id']);
            $arrayResonse[$key]['currency_code'] = "".fn_get_currencies_store();
            $arrayResonse[$key]['quantity'] = $value['amount'];
            $arrayResonse[$key]['category'] = "".fn_get_category_name_by_product_id($value['product_id']);
            $arrayResonse[$key]['image'] = "".fn_get_one_images_product_by_id($value['product_id']);
        }
    }
    return $arrayResonse;
}

function fn_sort_array_by_category_id( $array ) {
    usort($array, function($a, $b){
        return ($a['category_id'] - $b['category_id']);
    });
    return $array;
}

function fn_get_category_name_by_product_id_all( $productId ) {
    $response = [];
    $categoryIds = db_get_array("SELECT category_id FROM ?:products_categories WHERE product_id = ?i", $productId);
    foreach ( $categoryIds as $key => $value ) {
        $categoryList[$key] = db_get_fields("SELECT id_path FROM ?:categories WHERE category_id = ?i", $value['category_id'])[0];
    }
    foreach ( $categoryList  as $key => $value ) {
        $arrayCategotyList[$key] =  explode('/', $value);
    }
    foreach ( $arrayCategotyList as $key => $value ) {
        $response[$key] = fn_get_line_category_product($value);
    }
    return fn_sort_array_by_category_id($response);
}

function fn_get_line_category_product( $arrayCategotyList ) {
    $responseCategory = [];
    $array = [];
    foreach ($arrayCategotyList as $key => $value ) {
        $responseCategory['category_id'] = $value;
        $array[$key] = db_get_fields("SELECT category FROM ?:category_descriptions WHERE category_id = ?i", $value)[0];
    }
    $responseCategory['name'] = implode(' - ', $array);
    return $responseCategory;
}

function fn_get_category_name_by_product_id( $productId ) {
    $categoryId = db_get_fields("SELECT category_id FROM ?:products_categories WHERE product_id = ?i", $productId);
    $categoryName = db_get_fields("SELECT category FROM ?:category_descriptions WHERE category_id = ?i", $categoryId[0]);
    return $categoryName[0];
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

    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $usersPurchases = db_get_fields("SELECT DISTINCT user_id FROM ?:orders WHERE company_id = ?i", $company['company_id']);
            foreach ( $users as $key => $user ) {
                if ( !in_array($user['user_id'] , $usersPurchases) ) {
                    unset($users[$key]);
                }
            }
        }
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

    if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
        $userId = fn_get_user_id_by_token($_REQUEST['token']);
        $company = fn_get_company_id_user_type_by_token($userId);
        if ( $company['user_type'] != 'A' ) {
            $usersPurchases = db_get_fields("SELECT DISTINCT order_id FROM ?:orders WHERE company_id = ?i", $company['company_id']);
            foreach ( $orders as $key => $order ) {
                if ( !in_array($order['order_id'] , $usersPurchases) ) {
                    unset($orders[$key]);
                }
            }
        }
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
            $query .= 'WHERE status = \''.$statusCode.'\'';
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

        if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
            $userId = fn_get_user_id_by_token($_REQUEST['token']);
            $company = fn_get_company_id_user_type_by_token($userId);
            if ( $company['user_type'] != 'A' ) {
                $query .= " AND company_id = " . $company['company_id'];
            }
        }

    } else {
        $query = "WHERE status <> 'D'";
    }
    $query .= "  ORDER BY order_id DESC LIMIT " . (int)$data['limit'] . " OFFSET " . (int)$data['page'];
    $listOrders = db_get_array("SELECT * FROM ?:orders ". $query);
    $sum = 0;
    $quantity = 0;
    foreach ($listOrders as $value) {
        $sum = $sum + $value['total'];
        $quantity++;
    }
    $listOrders['totalsumm'] = "".$sum;
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
    $dataCategory = [];

    if ( isset($data['quantity']) && !empty($data['quantity']) ) {
        $dataProducts['amount'] = $data['quantity'];
    }
    if ( isset($data['code']) && !empty($data['code']) ) {
        $dataProducts['product_code'] = $data['code'];
    }
    if ( isset($data['status']) && !is_null($data['status']) ) {
        $status = false;
        if ( $data['status'] == '0' ) $status = 'D';
        else $status = 'A';
        $dataProducts['status'] = $status;
    }
    if ( isset($data['name']) && !empty($data['name']) ) {
        $dataDescription['product'] = $data['name'];
    }
    if ( isset($data['description']) && !empty($data['description']) ) {
        $dataDescription['full_description'] = $data['description'];
    }
    if ( isset($data['categories']) && !empty($data['categories']) ) {
        $dataCategory['category_id'] = $data['categories'];
    }
    if ( $data['product_id'] != '0' ) {
        $productId = $data['product_id'];
    } else {
        if (!empty($dataProducts)) {
            $dataProducts['company_id'] = Registry::get('runtime.company_id');
            if ( ( Registry::get('settings.module_admin.general.is_multivendor') == 'Y' ) || ( Registry::get('addons.module-admin.is_multivendor') == 'Y' ) ) {
                $userId = fn_get_user_id_by_token($_REQUEST['token']);
                $company = fn_get_company_id_user_type_by_token($userId);
                if ( $company['user_type'] != 'A' ) {
                    $dataProducts['company_id'] =  $company['company_id'];
                }
            }
            $productId = db_query("INSERT INTO ?:products ?e ", $dataProducts);
        }
    }

    if ( isset($_FILES['image']) && !empty($_FILES['image']) ) {
        $files = $_FILES['image'];
        $count = 0;
        foreach ( $files as $file ) {
            $image =  $files['tmp_name'][$count];
            $name = $files['name'][$count];
            if(is_uploaded_file($image)) {
                $width = getimagesize($image)[0];
                $height = getimagesize($image)[1];
                $nameImage = time().rand().$name;
                fn_add_images_to_product($nameImage, $width, $height, $productId, 'A');
                if ( fn_allowed_for('MULTIVENDOR') ) move_uploaded_file($image, 'images/detailed/2/' . basename($nameImage));
                else move_uploaded_file($image, 'images/detailed/1/' . basename($nameImage));
            }
            $count++;
        }
    }

    if ( isset($data['price']) && !empty($data['price']) ) {
        $dataPrice['price'] = $data['price'];
    }
    if ( $data['product_id'] != '0' ) {
        if ( !empty($dataCategory['category_id']) ) {
 
            db_query("DELETE FROM ?:products_categories WHERE product_id = ?i", $data['product_id']);
            foreach ( $dataCategory['category_id'] as $key => $value ) {
                $listCategories = [
                    'product_id' => $data['product_id'],
                    'category_id' => $value,
                    'link_type'   => 'M',
                    'position'   => 0
                ];
                $result = db_query("INSERT INTO ?:products_categories ?e ", $listCategories);
            }
        }
        if ( !empty($dataProducts) ) db_query("UPDATE ?:products SET ?u WHERE product_id = ?i", $dataProducts, $data['product_id']);
        if ( !empty($dataDescription) ) db_query("UPDATE ?:product_descriptions SET ?u WHERE product_id = ?i", $dataDescription, $data['product_id']);
        if ( !empty($dataPrice) ) db_query("UPDATE ?:product_prices SET ?u WHERE product_id = ?i", $dataPrice, $data['product_id']);
        $response = [];
        $response['product_id'] = $data['product_id'];
        $response['images']     = fn_get_array_images_product_by_id($data['product_id']);
        return $response;
    } else {

        if ( !empty($dataDescription) ) {
            $dataDescription['product_id'] = $productId;
            $dataDescription['lang_code'] = CART_LANGUAGE;
            db_query("INSERT INTO ?:product_descriptions ?e ", $dataDescription);
        }

        foreach ( $dataCategory['category_id'] as $key => $value ) {
            $listCategories = [
                'product_id' => $productId,
                'category_id' => $value,
                'link_type'   => 'M',
                'position'   => 0
            ];
            $result = db_query("INSERT INTO ?:products_categories ?e ", $listCategories);
        }

        if ( !empty($dataPrice) ) {
            $dataPrice['product_id'] = $productId;
            $dataPrice['lower_limit'] = 1;
            db_query("INSERT INTO  ?:product_prices ?e ", $dataPrice);
        }
        $response = [];
        $response['product_id'] = $productId;
        $imagesList = fn_get_array_images_product_by_id($productId);
        if ( !empty($imagesList) ) {
            $idMainImage = $imagesList[0]['image_id'];
            $data = [
                'type' => 'M'
            ];
            $mainImage = db_query("UPDATE ?:images_links SET ?u WHERE object_id = ?i AND detailed_id = ?i", $data, $productId, $idMainImage);
            if ( $mainImage == '1' )  $imagesList[0]['image_id'] = '-1';
        }
        $response['images']  = $imagesList;
        return $response;
    }
}

function fn_add_images_to_product( $imagePath, $width, $height, $productId, $type ) {
    $dataIm = array(
        'image_path' => $imagePath,
        'image_x' => $width,
        'image_y' => $height,
    );
    $addImage = db_query('INSERT INTO ?:images ?e', $dataIm);

    $dataNew = array(
        'object_id' => $productId,
        'object_type' => 'product',
        'image_id' => 0,
        'detailed_id' => $addImage,
        'type' => $type,
        'position' => 0,
    );
    $images = db_query('INSERT INTO ?:images_links ?e', $dataNew);
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

function fn_module_admin_place_order(&$cart, &$auth, $action = '', $issuer_id = null, &$parent_order_id = 0) {
//    $user_data = array(
//        'user_id' => '852',
//        'device_token' => '77777777',
//        'os_type' => $cart,
//    );
//    $newDevice = db_query('INSERT INTO ?:users_devices_module_admin ?e', $user_data);
    $orderId = $cart;
    $ids = [];
    $userDevices = db_get_array("SELECT * FROM ?:users_devices_module_admin WHERE 1");
    foreach ( $userDevices as $device ) {
        if (strtolower($device['os_type']) == 'ios') {
            $ids['ios'][] = $device['device_token'];
        } else {
            $ids['android'][] = $device['device_token'];
        }
    }
    $order = fn_get_order_by_id($orderId);
    if ( count($order)  > 0 ) {
        $msg = array(
            'body'       => number_format( $order[0]['total'], 2, '.', '' ),
            'title'      => "http://" . $_SERVER['HTTP_HOST'],
            'vibrate'    => 1,
            'sound'      => 1,
            'badge'      => 1,
            'priority'   => 'high',
            'new_order'  => [
                'order_id'      => $orderId,
                'total'         => number_format( $order[0]['total'], 2, '.', '' ),
                'currency_code' => "".fn_get_currencies_store(),
                'site_url'      => "http://" . $_SERVER['HTTP_HOST'],
            ],
            'event_type' => 'new_order'
        );

        $msg_android = array(
            'new_order'  => [
                'order_id'      => $orderId,
                'total'         => number_format( $order[0]['total'], 2, '.', '' ),
                'currency_code' => "".fn_get_currencies_store(),
                'site_url'      => "http://" . $_SERVER['HTTP_HOST'],
            ],
            'event_type' => 'new_order'
        );

        foreach ( $ids as $k => $mas ):
            if ( $k == 'ios' ) {
                $fields = array
                (
                    'registration_ids' => $ids[$k],
                    'notification'     => $msg,
                );
            } else {
                $fields = array
                (
                    'registration_ids' => $ids[$k],
                    'data'             => $msg_android
                );
            }
            fn_sendCurl( $fields );
         endforeach;
    }
}

function fn_sendCurl($fields)
{
    $API_ACCESS_KEY = 'AAAAlhKCZ7w:APA91bFe6-ynbVuP4ll3XBkdjar_qlW5uSwkT5olDc02HlcsEzCyGCIfqxS9JMPj7QeKPxHXAtgjTY89Pv1vlu7sgtNSWzAFdStA22Ph5uRKIjSLs5z98Y-Z2TCBN3gl2RLPDURtcepk';
    $headers = array
    (
        'Authorization: key=' . $API_ACCESS_KEY,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_exec($ch);
    curl_close($ch);
}

function fn_get_category_list( $categoryId ) {
    $response = [];
    $categoryList = db_get_array("SELECT category_id, level FROM ?:categories WHERE parent_id = ?i", $categoryId);
    if ( count($categoryList) > 0 ) {
        foreach ( $categoryList as $key => $value ) {
            $response[$key]['category_id'] = $value['category_id'];
            $response[$key]['name'] = fn_get_category_name_by_id($value['category_id']);
            if ( $value['level'] <= 2 ) $response[$key]['parent'] = true;
            else $response[$key]['parent'] = false;
        }
        return $response;
    } else {
        $categoryList = db_get_array("SELECT category_id, level FROM ?:categories WHERE category_id = ?i", $categoryId);
        foreach ( $categoryList as $key => $value ) {
            $response[$key]['category_id'] = $value['category_id'];
            $response[$key]['name'] = fn_get_category_name_by_id($value['category_id']);
            if ( $value['level'] <= 2 ) $response[$key]['parent'] = true;
            else $response[$key]['parent'] = false;
        }
        return $response;
    }
}

function fn_get_category_list_root() {
    $categoryList = db_get_array("SELECT category_id FROM ?:categories WHERE parent_id = 0");
    if ( count($categoryList) > 0 ) {
        foreach ( $categoryList as $key => $value ) {
            $response[$key]['category_id'] = $value['category_id'];
            $response[$key]['name'] = fn_get_category_name_by_id($value['category_id']);
            if ( $value['level'] <= 2 ) $response[$key]['parent'] = true;
            else $response[$key]['parent'] = false;
        }
        return $response;
    } else {
        return null;
    }
}

function fn_get_category_name_by_id( $categoryId ) {
    $name = db_get_fields("SELECT category FROM ?:category_descriptions WHERE category_id = ?i", $categoryId);
    return $name[0];
}
// -------------------------------- //

function fn_get_user_id_by_token( $token ) {
    return db_get_field("SELECT user_id FROM ?:users_module_admin WHERE token = ?s", $token);
}


function fn_get_company_id_user_type_by_token( $user_id ) {
    return db_get_array("SELECT user_type, company_id FROM ?:users WHERE user_id = ?i", $user_id)[0];
}

function fn_delete_main_image( $response ) {
    $answer = [];
    if ( $response['image_id'] == '-1' ) {
        $imageId = db_get_field("SELECT detailed_id FROM ?:images_links WHERE object_id = ?i AND type = 'M'", $response['product_id']);
        $delete = db_query("DELETE FROM ?:images_links WHERE object_id = ?i AND type = 'M'", $response['product_id']);
        $imagePath = db_get_field("SELECT image_path FROM ?:images WHERE image_id = ?i", $imageId);
        $answer['product_id'] = $response['product_id'];
        $answer['images'] = fn_get_array_images_product_by_id($response['product_id']);
        unlink('images/detailed/1/'.$imagePath);
    } else {
        $delete = db_query("DELETE FROM ?:images_links WHERE object_id = ?i AND detailed_id = ?i", $response['product_id'], $response['image_id']);
        $imagePath = db_get_field("SELECT image_path FROM ?:images WHERE image_id = ?i", $response['image_id']);
        $mainImage = db_query("DELETE FROM ?:images WHERE image_id = ?i", $response['image_id']);
        unlink('images/detailed/1/'.$imagePath);
        $answer['product_id'] = $response['product_id'];
        $answer['images'] = fn_get_array_images_product_by_id($response['product_id']);
    }
    return $answer;
}

function fn_print( $array ) {
    echo "<pre>";
    var_dump($array);
    die;
    echo "</pre>";
}


function get_productId_by_orderId( $orderId ) {
    $response = array_shift(db_get_array("SELECT product_id FROM ?:order_details WHERE order_id = ?i", $orderId));
    if ( isset($response['product_id']) ) {
        return $response['product_id'];
    } else return 0;
}

function fn_get_product_options_by_id( $productId ) {

    $optionIds = db_get_fields( "SELECT * FROM ?:product_options WHERE product_id = ?i", $productId );

    if ( count($optionIds) > 0) {
        $optionNames = [];
        foreach ( $optionIds as $key => $value ) {
            $optionNames[$key]['optionId'] = $value;
            $optionNames[$key]['name'] = db_get_field( "SELECT option_name FROM ?:product_options_descriptions WHERE option_id  = ?i", $value );
        }

        foreach ( $optionIds as $key => $value ) {
            $oneVariant = db_get_fields( "SELECT * FROM ?:product_option_variants WHERE option_id = ?i", $value );
            if ( count($oneVariant) > 0 ) {
                foreach ( $oneVariant as $keyVar => $var ) {
                    $varianName = array_shift(db_get_fields( "SELECT variant_name FROM ?:product_option_variants_descriptions WHERE variant_id = ?i", $var ));

                    $optionNames[$key]['values'][$keyVar]['variant_id'] = $var;
                    $optionNames[$key]['values'][$keyVar]['name'] = $varianName;
                }
            }
        }

        return $optionNames;
    }
    return '';
}
