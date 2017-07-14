<?php

//use Tygh\Helpdesk;
use Tygh\Registry;
//use Tygh\Development;

if (!defined('BOOTSTRAP')) { die('Access denied'); }
// http://cs-cart.pixy.pro/index.php?dispatch=module-admin.login
// https://www.cs-cart.ru/docs/4.5.x/developer_guide/core/db/functions.html#db-query-sql
// http://docs.cs-cart.com/4.0.x/core/db/db_placeholders.html
// https://www.cs-cart.ru/docs/4.2.x/developer/instruments/database/placeholders.html#w-where
// apidoc -i app/addons/module_admin/controllers/frontend/ -o apidoc/
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $API_VERSION = 2.0;
    $API_VERSION_SECOND = 2.0;

    /**
     * @api {post} login  Login
     * @apiVersion 1.0.0
     * @apiName Login
     * @apiGroup Login
     *
     * @apiParam {String} username User unique username.
     * @apiParam {Number} password User's  password.
     * @apiParam {String} os_type User's device's os_type for firebase notifications.
     * @apiParam {String} device_token User's device's token for firebase notifications.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} token  Token.
     * @apiSuccess {String} token  Token.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "version": 1.0,
     *       "response":
     *       {
     *          "token": "e9cf23a55429aa79c3c1651fe698ed7b",
     *       }
     *       "status": true
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Incorrect username or password",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'login' ) {
        $response = $_REQUEST;
        $auth_params = fn_get_user_params( $response['username'], $response['password'] );
        list($status, $user_data, $user_login, $password, $salt) = fn_auth_routines($auth_params, $auth);

        // Login incorrect
        if (empty($user_data) || empty($password) || fn_generate_salted_password($password, $salt) != $user_data['password']) {
            fn_log_event('users', 'failed_login', array (
                'user' => $response['username']
            ));
            fn_answer(['error' => 'Incorrect username or password', 'version' => $API_VERSION, 'status' => false]);
        }
        $user_id = fn_is_user_exists(0, array('email' => $response['username']));
        $userDevices = fn_get_users_devices( $user_id );
        if ( isset($response['device_token']) && !empty($response['device_token'] && isset($response['os_type']) && !empty($response['os_type'])) ) {
            $counter = 0;
            foreach ( $userDevices as $device ) {
                if ( $response['device_token'] == $device['device_token'] ) {
                    $counter++;
                }
            }
            if ( $counter == 0 ) {
                fn_set_user_device($user_id, $response['device_token'], $response['os_type'] );
            }
        }
        $token = fn_get_user_token($user_id);
        fn_answer( ['version' => $API_VERSION, 'response' => ['token' => implode($token)], 'status' => true] );
    }

    /**
     * @api {post} clientinfo  Clientinfo
     * @apiVersion 1.0.0
     * @apiName getClientInfo
     * @apiGroup Get clients info
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} client_id unique client ID.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} client_id  ID of the client.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {Number} total  Total sum of client's orders.
     * @apiSuccess {Number} quantity  Total quantity of client's orders.
     * @apiSuccess {String} email  Client's email.
     * @apiSuccess {String} phone  Client's telephone.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} cancelled  Total quantity of cancelled orders.
     * @apiSuccess {Number} completed  Total quantity of completed orders.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response"
     *   {
     *         "client_id" : "88",
     *         "fio" : "Anton Kiselev",
     *         "total" : "1006.00",
     *         "quantity" : "5",
     *         "cancelled" : "1",
     *         "completed" : "2",
     *         "email" : "client@mail.ru",
     *         "currency_code": "UAH",
     *         "phone" : "13456789"
     *   },
     *   "Status" : true,
     *   "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one client found",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    if ( $mode == 'clientinfo' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['client_id']) && !empty($response['client_id']) ) {
            $client_id = $response['client_id'];
            $clientInfo = fn_get_client_info($client_id);
            fn_answer([
                'version'   => $API_VERSION,
                'response'  => $clientInfo,
                'status' => true
            ]);
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
        }
    }

    /**
     * @api {post} clientorders  Get Client Orders
     * @apiVersion 1.0.0
     * @apiName clientorders
     * @apiGroup Get clients info
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} client_id unique client ID.
     * @apiParam {String} sort param for sorting orders(total/date_added/completed/cancelled).
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} order_id  ID of the order.
     * @apiSuccess {Number} order_number  Number of the order.
     * @apiSuccess {String} status  Status of the order.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} total  Total sum of the order.
     * @apiSuccess {Date} date_added  Date added of the order.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response"
     *   {
     *       "orders":
     *          {
     *             "order_id" : "1",
     *             "order_number" : "1",
     *             "status" : "Complete",
     *             "currency_code": "UAH",
     *             "total" : "106.00",
     *             "date_added" : "2016-12-09 16:17:02"
     *          },
     *          {
     *             "order_id" : "2",
     *             "order_number" : "2",
     *             "currency_code": "UAH",
     *             "status" : "Canceled",
     *             "total" : "506.00",
     *             "date_added" : "2016-10-19 16:00:00"
     *          }
     *    },
     *    "Status" : true,
     *    "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "You have not specified ID",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    if ( $mode == 'clientorders' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['client_id']) && !empty($response['client_id']) ) {
            $client_id = $response['client_id'];
            if ( isset($response['sort']) && !empty($response['sort']) ) {
                switch ( $response['sort'] ) {
                    case 'total':
                        $clientOrders = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i ORDER BY total DESC", $client_id);
                        break;
                    case 'date_added' :
                        $sort = '';
                        $clientOrders = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i ORDER BY timestamp DESC", $client_id);
                        break;
                    case 'completed'  :
                        $clientOrders = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i AND status = ?s", $client_id, 'C');
                        break;
                    case 'cancelled'  :
                        $clientOrders = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i AND status = ?s", $client_id, 'D');
                        break;
                    default :
                        $clientOrders = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i ORDER BY timestamp DESC", $client_id);
                }
            } else {
                $clientOrders = db_get_array("SELECT * FROM ?:orders WHERE user_id = ?i ORDER BY timestamp DESC", $client_id);
            }
            $orders['orders'] = fn_get_client_orders( $clientOrders );

            if ( count($orders['orders']) ) {
                fn_answer([
                    'response'  => $orders,
                    'version'   => $API_VERSION,
                    'status' => true
                ]);
            } else {
                fn_answer(['version' => $API_VERSION, 'response' => ['orders' => []], 'status' => true]);
            }
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
        }
    }

    /**
     * @api {post} orderhistory  Get Order History
     * @apiVersion 1.0.0
     * @apiName getOrderHistory
     * @apiGroup Get orders info
     *
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} name     Status of the order.
     * @apiSuccess {String} order_status_id  ID of the status of the order.
     * @apiSuccess {Date} date_added  Date of adding status of the order.
     * @apiSuccess {String} comment  Some comment added from manager.
     * @apiSuccess {Array} statuses  Statuses list for order.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *       {
     *           "response":
     *               {
     *                   "orders":
     *                      {
     *                          {
     *                              "name": "Отклонен",
     *                              "order_status_id": "5",
     *                              "date_added": "2017-05-07 16:05:37"
     *                          },
     *                       },
     *                    "statuses":
     *                        {
     *                          {
     *                               "name": "Обработан",
     *                               "order_status_id": "1",
     *                               "language_id": "1"
     *                           },
     *                           {
     *                               "name": "Выполнен",
     *                               "order_status_id": "2",
     *                               "language_id": "1"
     *                           },
     *                         }
     *               },
     *           "status": true,
     *           "version": 1.0
     *       }
     * @apiErrorExample Error-Response:
     *
     *     {
     *          "error": "Can not found any statuses for order with id = 5",
     *          "version": 1.0,
     *          "Status" : false
     *     }
     */
    if ( $mode == 'orderhistory' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['order_id']) && !empty($response['order_id']) ) {
            $orderId = $response['order_id'];
            $orderHistory = fn_get_order_history($orderId);
            fn_answer(['version' => $API_VERSION, 'response' => $orderHistory, 'status' => true]);
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
        }
    }


    /**
     * @api {post} deletedevicetoken  Delete User Device Token
     * @apiVersion 1.0.0
     * @apiName deleteUserDeviceToken
     * @apiGroup Tokens
     *
     * @apiParam {String} old_token User's device's token for firebase notifications.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "response":
     *       {
     *          "status": true,
     *          "version": 1.0
     *       }
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Missing some params",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'deletedevicetoken' ) {
        $response = $_REQUEST;
        if ( isset( $response['old_token'] ) && !empty($response['old_token']) ) {
            $deleteToken = fn_delete_user_device_token( $response['old_token'] );
            if ( $deleteToken !== false ) {
                fn_answer(['response' => ['version' => $API_VERSION, 'status' => true]]);
            } else {
                fn_answer(['version' => $API_VERSION, 'error' => 'Can not find your token', 'status' => false]);
            }
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'Missing some params', 'status' => false]);
        }
    }

    /**
     * @api {post} updatedevicetoken  Update User Device Token
     * @apiVersion 1.0.0
     * @apiName updateUserDeviceToken
     * @apiGroup Tokens
     *
     * @apiParam {String} new_token User's device's new token for firebase notifications.
     * @apiParam {String} old_token User's device's old token for firebase notifications.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "response":
     *       {
     *          "status": true,
     *          "version": 1.0
     *       }
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Missing some params",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'updatedevicetoken' ) {
        $response = $_REQUEST;
        if ( isset($response['old_token']) && !empty($response['old_token']) &&   isset($response['new_token']) && !empty($response['new_token']) ) {
            $updateToken = fn_update_user_device_token( $response['old_token'], $response['new_token']);
            if ( $updateToken !== false ) {
                fn_answer(['response' => ['version' => $API_VERSION, 'status' => true]]);
            } else {
                fn_answer(['version' => $API_VERSION, 'error' => 'Can not find your token', 'status' => false]);
            }
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'Missing some params', 'status' => false]);
        }
    }

    /**
     * @api {post} paymentanddelivery  Get Order Payment And Delivery
     * @apiVersion 1.0.0
     * @apiName paymentanddelivery
     * @apiGroup Get orders info
     *
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} payment_method     Payment method.
     * @apiSuccess {String} shipping_method  Shipping method.
     * @apiSuccess {String} shipping_address  Shipping address.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *
     *      {
     *          "response":
     *              {
     *                  "payment_method" : "Оплата при доставке",
     *                  "shipping_method" : "Доставка с фиксированной стоимостью доставки",
     *                  "shipping_address" : "проспект Карла Маркса 1, Днепропетровск, Днепропетровская область, Украина."
     *              },
     *          "status": true,
     *          "version": 1.0
     *      }
     * @apiErrorExample Error-Response:
     *
     *    {
     *      "error": "Can not found order with id = 90",
     *      "version": 1.0,
     *      "Status" : false
     *   }
     *
     */
    if ( $mode == 'paymentanddelivery' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['order_id']) && !empty($response['order_id']) ) {
            $order_id = $response['order_id'];
            $oderInfo = fn_get_payment_and_shipping_by_id($order_id);
            if ($oderInfo['error'] == null) {
                fn_answer(['version' => $API_VERSION, 'response' => $oderInfo['answer'], 'status' => true]);
            } else {
                fn_answer(['version' => $API_VERSION, 'error' => 'Can not found order with id = ' . $order_id, 'status' => false]);
            }
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'Missing some params', 'status' => false]);
        }
    }

    /**
     * @api {post} changeorderdelivery  Change Order Delivery
     * @apiVersion 1.0.0
     * @apiName changeorderdelivery
     * @apiGroup Change
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {String} address New shipping address.
     * @apiParam {String} city New shipping city.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} response Status of change address.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *         "status": true,
     *         "version": 1.0
     *    }
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Can not change address",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'changeorderdelivery' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['order_id']) && !empty($response['order_id']) ) {
            $order_id = $response['order_id'];
            $order = fn_get_order_by_id( $order_id );
            if ( count($order) > 0 ) {
                if ( isset($response['address']) && !empty($response['address']) ) {
                    fn_set_new_address_for_order_by_id($order_id, $response['address']);
                }
                if ( isset($response['city']) && !empty($response['city']) ) {
                    fn_set_new_city_for_order_by_id($order_id, $response['city']);
                }
                fn_answer(['version' => $API_VERSION, 'status' => true]);
            } else {
                fn_answer(['version' => $API_VERSION, 'error' => 'Can not change address', 'status' => false]);
            }
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'Missing some params', 'status' => false]);
        }
    }

    /**
     * @api {post} getclients  Get Clients
     * @apiVersion 1.0.0
     * @apiName getclients
     * @apiGroup Get clients info
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page number of the page.
     * @apiParam {Number} limit limit of the orders for the page.
     * @apiParam {String} fio full name of the client.
     * @apiParam {String} sort param for sorting clients(sum/quantity/date_added).
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} client_id  ID of the client.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {Number} total  Total sum of client's orders.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} quantity  Total quantity of client's orders.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response"
     *   {
     *     "clients"
     *      {
     *          {
     *              "client_id" : "88",
     *              "fio" : "Anton Kiselev",
     *              "total" : "1006.00",
     *              "currency_code": "UAH",
     *              "quantity" : "5"
     *          },
     *          {
     *              "client_id" : "10",
     *              "fio" : "Vlad Kochergin",
     *              "currency_code": "UAH",
     *              "total" : "555.00",
     *              "quantity" : "1"
     *          }
     *      }
     *    },
     *    "Status" : true,
     *    "version": 1.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one client found",
     *      "version": 1.0,
     *      "Status" : false
     * }
     *
     *
     */
    if ( $mode == 'getclients' ) {
        $response = $_REQUEST;
//        $error = fn_check_token();
//        if ($error !== null) {
//            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
//        }
        if (isset($response['page']) && (int)$response['page'] != 0 && (int)$response['limit'] != 0 && isset($response['limit'])) {
            $page = $response['page'];
            $limit = $response['limit'];
        } else {
            $page = 0;
            $limit = 20;
        }
        if (isset($response['sort']) && !empty($response['sort'])) {
            $sort = $response['sort'];
        } else {
            $sort = 'date_added';
        }
        if (isset($response['fio']) && !empty($response['fio']) ) {
            $fio = $response['fio'];
        } else {
            $fio = '';
        }
        $clients = fn_get_clients(['page' => $page, 'limit' => $limit, 'sort' => $sort, 'fio' => $fio]);
        if (count($clients) > 0) {
            $arrayAnswer['clients'] = $clients;
        } else {
            $arrayAnswer['clients'] = [];
        }
        fn_answer(['version' => $API_VERSION, 'response' => $arrayAnswer, 'status' => true]);
    }


    /**
     * @api {post} orderproducts  Get Order Products
     * @apiVersion 1.0.0
     * @apiName orderproducts
     * @apiGroup Get orders info
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {ID} order_id unique order id.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Url} image  Picture of the product.
     * @apiSuccess {Number} quantity  Quantity of the product.
     * @apiSuccess {String} name     Name of the product.
     * @apiSuccess {String} model    Model of the product.
     * @apiSuccess {Number} Price  Price of the product.
     * @apiSuccess {Number} total_order_price  Total sum of the order.
     * @apiSuccess {Number} total_price  Sum of product's prices.
     * @apiSuccess {String} currency_code  currency of the order.
     * @apiSuccess {Number} shipping_price  Cost of the shipping.
     * @apiSuccess {Number} total  Total order sum.
     * @apiSuccess {Number} product_id  unique product id.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *      "response":
     *          {
     *              "products": [
     *              {
     *                  "image" : "http://magento_site_url/media/catalog/product/cache/1/thumbnail/200x200/9df78eab33525d08d6e5fb8d27136e95/w/p/wpd005t.jpg",
     *                  "name" : "DUMBO Boyfriend Jea",
     *                  "model": "P0222KUH4T",
     *                  "quantity" : 1,
     *                  "price" : 115.50,
     *                  "product_id" : 427
     *              },
     *              {
     *                  "image" : "http://magento_site_url/media/catalog/product/cache/1/thumbnail/200x200/9df78eab33525d08d6e5fb8d27136e95/h/d/hdd006_1.jpg",
     *                  "name" : "Geometric Candle Holders",
     *                  "model": "GGGHHIOJIJ33",
     *                  "quantity" : 3,
     *                  "price" : 45.00,
     *                  "product_id" : 391
     *               }
     *            ],
     *            "total_order_price":
     *              {
     *                   "total_discount": 0,
     *                   "total_price": 250.50,
     *                    "currency_code": "RUB",
     *                   "shipping_price": 36.75,
     *                   "total": 287.25
     *               }
     *
     *         },
     *      "status": true,
     *      "version": 1.0
     * }
     *
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *          "error": "Can not found any products in order with id = 10",
     *          "version": 1.0,
     *          "Status" : false
     *     }
     *
     */
    if ( $mode == 'orderproducts' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['order_id']) && !empty($response['order_id']) ) {
            $order_id = $response['order_id'];
            $productItems = fn_get_product_info_by_id_order( $order_id );
            fn_answer(['version' => $API_VERSION, 'response' => $productItems, 'status' => true]);
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'Can not found any products in order with id = '.$response['order_id'], 'status' => false]);
        }
    }

    /**
     * @api {post} productinfo  Get Product Info
     * @apiVersion 2.0.0
     * @apiName productinfo
     * @apiGroup Get product info
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} product_id unique product ID.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} product_id  ID of the product.
     * @apiSuccess {String} name  Name of the product.
     * @apiSuccess {String} status  Product Status.
     * @apiSuccess {String} model    Model of the product.
     * @apiSuccess {Number} price  Price of the product.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} quantity  Actual quantity of the product.
     * @apiSuccess {String} description  Detail description of the product.
     * @apiSuccess {String} categoryName  Category name of the product.
     * @apiSuccess {Array} images  Array of the images of the product.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response":
     *   {
     *       "product_id" : "392",
     *       "status": "Вкл.",
     *       "name" : "Madison LX2200",
     *       "model": "P0222KUH4T",
     *       "price" : "425.00",
     *       "currency_code": "UAH"
     *       "quantity" : "2",
     *       "categoryName" : "Настольные ПК"
     *       "description" : "10x Optical Zoom with 24mm Wide-angle and close up.10.7-megapixel backside illuminated CMOS sensor for low light shooting.  3" Multi-angle LCD. SD/SDXC slot. Full HD Video. High speed continuous shooting (up to 5 shots in approx one second) Built in GPS. Easy Panorama. Rechargable Li-ion battery. File formats: Still-JPEG, Audio- WAV, Movies-MOV. Image size: up to 4600x3400. Built in flash. 3.5" x 5" x 4". 20oz.",
     *       "images" :
     *       [
     *           [
     *              "image_id": "1430",
     *              "image" : "http://magento_site_url/media/catalog/product/cache/1/thumbnail/200x200/9df78eab33525d08d6e5fb8d27136e95/h/d/hde001a.jpg",
     *           ]
     *       ]
     *   },
     *   "Status" : true,
     *   "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Can not found product with id = 10",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */
    if ( $mode == 'productinfo' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['product_id']) && !empty($response['product_id']) ) {
            $product_id = $response['product_id'];
            $productItems = fn_get_product_info_by( $product_id );
            if ( $productItems !== '' ) {
                fn_answer(['version' => $API_VERSION_SECOND, 'response' => $productItems, 'status' => true]);
            } else {
                fn_answer(['version' => $API_VERSION_SECOND, 'error' => 'Can not found order with id = '.$product_id, 'status' => false]);
            }
        } else {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => 'You have not specified ID', 'status' => false]);
        }
    }

    /**
     * @api {post} productslist  Get Products List
     * @apiVersion 2.0.0
     * @apiName productslist
     * @apiGroup Get product info
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page number of the page.
     * @apiParam {Number} limit limit of the orders for the page.
     * @apiParam {String} name name of the product for search.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} product_id  ID of the product.
     * @apiSuccess {String} name  Name of the product.
     * @apiSuccess {String} model    Model of the product.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} price  Price of the product.
     * @apiSuccess {Number} quantity  Actual quantity of the product.
     * @apiSuccess {String} categoryName  Category name.
     * @apiSuccess {Url} image  Url to the product image.
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response":
     *   {
     *      "products":
     *      {
     *           {
     *             "product_id" : "1",
     *             "name" : "HTC Touch HD",
     *             "model": "P0222KUH4T",
     *             "price" : "100.00",
     *             "currency_code": "UAH",
     *             "quantity" : "83",
     *             "categoryName": "LED телевизоры",
     *             "image" : "http://site-url/image/catalog/demo/htc_touch_hd_1.jpg"
     *           },
     *           {
     *             "product_id" : "2",
     *             "name" : "iPhone",
     *             "model": "P0222KUH4T",
     *             "price" : "300.00",
     *             "currency_code": "UAH",
     *             "quantity" : "30",
     *             "categoryName": "LED телевизоры",
     *             "image" : "http://site-url/image/catalog/demo/iphone_1.jpg"
     *           }
     *      }
     *   },
     *   "Status" : true,
     *   "version": 2.0
     * }
     * @apiErrorExample Error-Response:
     * {
     *      "Error" : "Not one product not found",
     *      "version": 2.0,
     *      "Status" : false
     * }
     *
     *
     */
    if ( $mode == 'productslist' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => $error, 'status' => false]);
        }
        if (isset($response['page']) && (int)$response['page'] != 0 && (int)$response['limit'] != 0 && isset($response['limit'])) {
            $page = $response['page'] = ($response['page'] * $response['limit']) - $response['limit'];
            $limit = $response['limit'];
        } else {
            $page = 0;
            $limit = 20;
        }
        if ( isset($response['name']) && !empty($response['name']) ) {
            $name = $response['name'];
        } else {
            $name = '';
        }
        $productList['products'] = fn_get_product_list( $page, $limit, $name );
        fn_answer(['version' => $API_VERSION_SECOND, 'response' => $productList, 'status' => true]);
    }

    /**
     * @api {post} statistic  Statistic
     * @apiVersion 1.0.0
     * @apiName statistic
     * @apiGroup Get dashboard statistics
     *
     * @apiParam {String} filter Period for filter(day/week/month/year).
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Array} xAxis Period of the selected filter.
     * @apiSuccess {Array} Clients Clients for the selected period.
     * @apiSuccess {Array} Orders Orders for the selected period.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Number} total_sales  Sum of sales of the shop.
     * @apiSuccess {Number} sale_year_total  Sum of sales of the current year.
     * @apiSuccess {Number} orders_total  Total orders of the shop.
     * @apiSuccess {Number} clients_total  Total clients of the shop.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *           "response": {
     *               "xAxis": [
     *                  1,
     *                  2,
     *                  3,
     *                  4,
     *                  5,
     *                  6,
     *                  7
     *              ],
     *              "clients": [
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0
     *              ],
     *              "orders": [
     *                  1,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0,
     *                  0
     *              ],
     *              "total_sales": "1920.00",
     *              "sale_year_total": "305.00",
     *              "currency_code": "UAH",
     *              "orders_total": "4",
     *              "clients_total": "3"
     *           },
     *           "status": true,
     *           "version": 1.0
     *  }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error": "Unknown filter set",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'statistic' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['filter']) && !empty($response['filter']) ) {
            $clients = fn_get_total_customers(['filter' => $response['filter']]);
            $orders = fn_get_total_orders(['filter' => $response['filter']]);
            $data = [];
            if ($clients === false || $orders === false) {
                fn_answer(['error' => 'Unknown filter set', 'status' => false]);
            } else {
                $clients_for_time = [];
                $orders_for_time = [];
                if ($response['filter'] == 'month') {
                    $hours = range(1, 30);
                    for ($i = 1; $i <= 30; $i++) {
                        $b = 0;
                        $o = 0;
                        foreach ($clients as $value) {
                            $day = $value['timestamp'];
                            $day = date("d", $day);
                            if ($day == $i) {
                                $b = $b + 1;
                            }
                        }
                        $clients_for_time[] = $b;
                        foreach ($orders as $value) {
                            $day = $value['timestamp'];
                            $day = date("d", $day);
                            if ($day == $i) {
                                $o = $o + 1;
                            }
                        }
                        $orders_for_time[] = $o;
                    }
                } elseif ($response['filter'] == 'day') {
                    $hours = range(0, 23);
                    for ($i = 0; $i <= 23; $i++) {
                        $b = 0;
                        $o = 0;
                        foreach ($clients as $value) {
                            $hour = $value['timestamp'];
                            $hour = date("h", $hour);
                            if ($hour == $i) {
                                $b = $b + 1;
                            }
                        }
                        $clients_for_time[] = $b;
                        foreach ($orders as $value) {
                            $day = $value['timestamp'];
                            $day = date("h", $day);
                            if ($day == $i) {
                                $o = $o + 1;
                            }
                        }
                        $orders_for_time[] = $o;
                    }
                } elseif ($response['filter'] == 'week') {
                    $hours = range(1, 7);
                    for ($i = 1; $i <= 7; $i++) {
                        $b = 0;
                        $o = 0;
                        foreach ($clients as $value) {
                            $date = $value['timestamp'];
                            $f = date("N", $date);
                            if ($f == $i) {
                                $b = $b + 1;
                            }
                        }
                        $clients_for_time[] = $b;
                        foreach ($orders as $val) {
                            $day = $val['timestamp'];
                            $day = date("N", $day);
                            if ($day == $i) {
                                $o = $o + 1;
                            }
                        }
                        $orders_for_time[] = $o;
                    }
                } elseif ($response['filter'] == 'year') {
                    $hours = range(1, 12);
                    for ($i = 1; $i <= 12; $i++) {
                        $b = 0;
                        $o = 0;
                        foreach ($clients as $value) {
                            $date = $value['timestamp'];
                            $f = date("m", $date);
                            if ($f == $i) {
                                $b = $b + 1;
                            }
                        }
                        $clients_for_time[] = $b;
                        foreach ($orders as $val) {
                            $day = $val['timestamp'];
                            $day = date("m", $day);
                            if ($day == $i) {
                                $o = $o + 1;
                            }
                        }
                        $orders_for_time[] = $o;
                    }
                }
                $data['xAxis'] = $hours;
                $data['clients'] = $clients_for_time;
                $data['orders'] = $orders_for_time;
                $data['total_sales'] = "".get_total_sales();
                $data['sale_year_total'] = "".get_total_sales(['this_year' => true]);
                $data['orders_total'] = fn_get_total_orders();
                $data['clients_total'] = fn_get_total_customers();
                $data['currency_code'] = "".fn_get_currencies_store();
                fn_answer(['version' => $API_VERSION, 'response' => $data, 'status' => true]);
            }
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'Missing some params', 'status' => false]);
        }
    }

    /**
     * @api {post} orderinfo  Get Order Info
     * @apiVersion 1.0.0
     * @apiName orderinfo
     * @apiGroup Get orders info
     *
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Number} order_number  Number of the order.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {String} status  Status of the order.
     * @apiSuccess {String} email  Client's email.
     * @apiSuccess {Number} phone  Client's phone.
     * @apiSuccess {Number} total  Total sum of the order.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {Date} date_added  Date added of the order.
     * @apiSuccess {Array} statuses  Statuses list for order.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *      "response" :
     *          {
     *              "order_number" : "6",
     *              "currency_code": "RUB",
     *              "fio" : "Anton Kiselev",
     *              "email" : "client@mail.ru",
     *              "phone" : "056 000-11-22",
     *              "date_added" : "2016-12-24 12:30:46",
     *              "total" : "1405.00",
     *              "status" : "Отклонен",
     *              "statuses" :
     *                  {
     *                       {
     *                          "name": "Обработан",
     *                          "order_status_id": "1",
     *                          "language_id": "1"
     *                       },
     *                       {
     *                          "name": "Выполнен",
     *                          "order_status_id": "2",
     *                          "language_id": "1"
     *                       },
     *                       {
     *                          "name": "Открыт",
     *                          "order_status_id": "3",
     *                          "language_id": "1"
     *                       },
     *                    }
     *          },
     *      "status" : true,
     *      "version": 1.0
     * }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Can not found order with id = 5",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     */
    if ( $mode == 'orderinfo' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        $data = [];
        if ( isset($response['order_id']) && !empty($response['order_id']) ) {
            $orderId = $response['order_id'];
            $orders = fn_get_order_by_id($orderId);
            if ( count($orders) > 0 ) {
                $orders = end($orders);
                $data['order_number'] = $orders['order_id'];
                $data['fio'] = $orders['b_firstname']. " " . $orders['b_lastname'];
                $data['email'] = $orders['email'];
                $data['telephone'] = $orders['phone'];
                $data['total'] = $orders['total'];
                $data['currency_code'] = "".fn_get_currencies_store();
                $data['date_added'] = date("Y-m-d H:m:s", $orders['timestamp']);
                $data['status'] = fn_get_name_order_status($orders['status']);
                $data['statuses'] = fn_get_status_all_order();
                fn_answer(['version' => $API_VERSION, 'response' => $data, 'status' => true]);
            } else {
                fn_answer(['version' => $API_VERSION, 'error' => 'Can not found order with id = ' . $orderId, 'status' => false]);
            }
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
        }
    }

    /**
     * @api {post} orders  Get Orders
     * @apiVersion 1.0.0
     * @apiName orders
     * @apiGroup Get orders info
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} page=0 number of the page.
     * @apiParam {Number} limit=9999 limit of the orders for the page.
     * @apiParam {String} [fio] full name of the client.
     * @apiParam {String} [order_status_id] unique id of the order.
     * @apiParam {Number} [min_price=1] min price of order.
     * @apiParam {Number} [max_price='max order price'] max price of order.
     * @apiParam {Date} [date_min] min date adding of the order.
     * @apiParam {Date} [date_max] max date adding of the order.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Array} orders  Array of the orders.
     * @apiSuccess {Array} statuses  Array of the order statuses.
     * @apiSuccess {Number} order_id  ID of the order.
     * @apiSuccess {Number} order_number  Number of the order.
     * @apiSuccess {String} fio     Client's FIO.
     * @apiSuccess {String} status  Status of the order.
     * @apiSuccess {String} currency_code  Default currency of the shop.
     * @apiSuccess {String} order[currency_code] currency of the order.
     * @apiSuccess {Number} total  Total sum of the order.
     * @apiSuccess {Date} date_added  Date added of the order.
     * @apiSuccess {Date} total_quantity  Total quantity of the orders.
     *
     *
     *
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     * {
     *   "Response"
     *   {
     *      "orders":
     *      {
     *            {
     *             "order_id" : "1",
     *             "order_number" : "1",
     *             "fio" : "Anton Kiselev",
     *             "status" : "Complete",
     *             "total" : "106.00",
     *             "date_added" : "2016-12-09 16:17:02",
     *             "currency_code": "RUB"
     *             },
     *            {
     *             "order_id" : "2",
     *             "order_number" : "2",
     *             "fio" : "Vlad Kochergin",
     *             "status" : "Pending",
     *             "total" : "506.00",
     *             "date_added" : "2016-10-19 16:00:00",
     *             "currency_code": "RUB"
     *             }
     *       },
     *       "statuses" :
     *       {
     *                       {
     *                          "name": "Обработан",
     *                          "order_status_id": "1",
     *                          "language_id": "1"
     *                       },
     *                       {
     *                          "name": "Выполнен",
     *                          "order_status_id": "2",
     *                          "language_id": "1"
     *                       },
     *                       {
     *                          "name": "Открыт",
     *                          "order_status_id": "3",
     *                          "language_id": "1"
     *                       },
     *       },
     *       "currency_code": "RUB",
     *       "total_quantity": 50,
     *       "total_sum": "2026.00",
     *       "max_price": "1405.00"
     *   },
     *   "Status" : true,
     *   "version": 1.0.0
     * }
     * @apiErrorExample Error-Response:
     *
     * {
     *      "version": 1.0.0,
     *      "Status" : false
     *
     * }
     *
     *
     */
    if ( $mode == 'orders' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if (isset($response['page']) && (int)$response['page'] != 0 && (int)$response['limit'] != 0 && isset($response['limit'])) {
            $page = $response['page'] = ($response['page'] * $response['limit']) - $response['limit'];
            $limit = $response['limit'];
        } else {
            $page = 0;
            $limit = 20;
        }
        if ( isset($response['fio']) || isset($response['order_status_id']) || isset($response['min_price'])
            || isset($response['max_price']) || isset($response['date_min']) || isset($response['date_max']) ) {
            $filter = [];
            if ( isset($response['fio']) && !empty($response['fio']) ) {
                $filter['fio'] = $response['fio'];
            }
            if ( isset($response['order_status_id']) && !empty($response['order_status_id']) ) {
                $filter['order_status_id'] = $response['order_status_id'];
            }
            if ( isset($response['min_price']) && !empty($response['min_price']) ) {
                $filter['min_price'] = $response['min_price'];
            } else $filter['min_price'] = 1;
            if ( isset($response['max_price']) && !empty($response['max_price']) ) {
                $filter['max_price'] = $response['max_price'];
            } else $filter['max_price'] = fn_get_max_order_price();
            if ( isset($response['date_min']) && !empty($response['date_min']) ) {
                $filter['date_min'] = $response['date_min'];
            }
            if ( isset($response['date_max']) && !empty($response['date_max']) ) {
                $filter['date_max'] = $response['date_max'];
            }
            $orders = fn_get_orders_castom(['filter' => $filter, 'page' => $page, 'limit' => $limit]);
        } else {
            $orders = fn_get_orders_castom(['page' => $page, 'limit' => $limit]);
        }

        $response = [];
        $orders_to_response = [];
//        fn_answer($orders);
        foreach ($orders as $order) {
            if ($order['order_id'] !== null) {
                $data['order_number'] = $order['order_id'];
                $data['order_id'] = $order['order_id'];
                $data['fio'] = $order['b_firstname'] . ' ' . $order['b_lastname'];
                $data['status'] = fn_get_name_order_status($order['status']);
                $data['total'] = "".$order['total'];
                $data['date_added'] = date('Y-m-d H:m:s', $order['timestamp']);
                $data['currency_code'] = "".fn_get_currencies_store();
                $orders_to_response[] = $data;
            }
        }

        $response['orders'] = $orders_to_response;
        $response['total_quantity'] = $orders['quantity'];
        $response['currency_code'] = "".fn_get_currencies_store();
        $response['total_sum'] = $orders['totalsumm'];

        $response['max_price'] = fn_get_max_order_price();
        $response['statuses'] = fn_get_status_all_order();
        fn_answer(['version' => $API_VERSION, 'response' => $response, 'status' => true]);
    }

    /**
     * @api {post} changestatus  Change Status
     * @apiVersion 1.0.0
     * @apiName changestatus
     * @apiGroup Change
     *
     * @apiParam {Number} order_id unique order ID.
     * @apiParam {String} status_id unique status ID.
     * @apiParam {Token} token your unique token.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} name Name of the new status.
     * @apiSuccess {String} date_added Date of adding status.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *          "response":
     *              {
     *                  "name" : "Выполнен",
     *                  "date_added" : "2016-12-27 12:01:51"
     *              },
     *          "status": true,
     *          "version": 1.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Missing some params",
     *       "version": 1.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'changestatus' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['order_id']) && !empty($response['order_id']) && isset($response['status_id']) && !empty($response['status_id']) ) {
            $statusId = fn_get_status_id_castom($response['status_id']);
            if ( $statusId !== false ) {
                fn_change_order_status($response['order_id'], $statusId, '');
                $answer['name'] = fn_get_name_order_status($statusId);
                $answer['date_added'] = date('Y-m-d H:m:s', time());
                fn_answer(['version' => $API_VERSION, 'response' => $answer, 'status' => true]);
            } else fn_answer(['version' => $API_VERSION, 'error' => 'You have not specified status ID', 'status' => false]);
        } else {
            fn_answer(['version' => $API_VERSION, 'error' => 'You have not specified ID', 'status' => false]);
        }
    }

    /**
     * @api {post} updateproduct  Update Product
     * @apiVersion 2.0.0
     * @apiName updateproduct
     * @apiGroup Set Product
     *
     * @apiParam {Token} token your unique token.
     * @apiParam {Number} product_id unique product ID.
     * @apiParam {Number} quantity unique product ID.
     * @apiParam {String} name new product name.
     * @apiParam {String} description new product description.
     * @apiParam {String} model new product model.
     * @apiParam {Number} status new product status.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {String} name Name of the new status.
     * @apiSuccess {String} date_added Date of adding status.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "status": true,
     *       "version": 2.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Missing some params",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'updateproduct' ) {
        $response = $_REQUEST;
        if ( isset($response['description']) && !empty($response['description'])) {
            $response['description'] = $_POST['description'];
        }

        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['product_id']) ) {
            fn_answer(['version' => $API_VERSION_SECOND, 'response' => fn_update_product_new($response), 'status' => true]);
        } else fn_answer(['version' => $API_VERSION_SECOND, 'error' => 'You have not specified ID', 'status' => false]);
    }

    /**
     * @api {post} getcategories  Get Categories
     * @apiVersion 2.0.0
     * @apiName getcategories
     * @apiGroup Category
     *
     * @apiParam {Token} token your unique token.
     *
     * @apiParam {array} Category list.
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     *  HTTP/1.1 200 OK
     *   {
     *          "response":
     *                  {
     *                       "id": "165",
     *                       "name": "Планшеты",
     *                       "parent": false
     *                       },
     *                   {
     *                       "id": "166",
     *                       "name": "Электроника",
     *                       "parent": true
     *                   },
     *          "status": true,
     *          "version": 2.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Category list is empty",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'getcategories' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => $error, 'status' => false]);
        }
        $list = [];
        if ( $response['category_id'] == '-1' ) {
            $list = fn_get_category_list_root();
        } else {
            $list = fn_get_category_list($response['category_id']);
        }
        $responseNew['categories'] = $list;
        if ( !is_null($responseNew['categories']) ) {
            fn_answer(['version' => $API_VERSION_SECOND, 'response' => $responseNew, 'status' => true]);
        } else {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => 'Category list is empty', 'status' => false]);
        }
    }

    /**
     * @api {post} mainimage  Set main image
     * @apiVersion 2.0.0
     * @apiName mainimage
     * @apiGroup Image
     *
     * @apiParam {string} token your unique token.
     * @apiParam {number} image_id Image id.
     * @apiParam {number} product_id Product id.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "status": true,
     *       "version": 2.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Not enough parameters",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'mainimage' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['image_id']) && !empty($response['image_id']) &&
            isset($response['product_id']) && !empty($response['product_id']) ) {
            $data = [
                'type' => 'A'
            ];
            $mainImage = db_query("UPDATE ?:images_links SET ?u WHERE object_id = ?i AND type = 'M'", $data, $response['product_id']);
            $data = [
                'type' => 'M'
            ];
            $mainImage = db_query("UPDATE ?:images_links SET ?u WHERE object_id = ?i AND detailed_id = ?i", $data, $response['product_id'], $response['image_id']);
            fn_answer(['version' => $API_VERSION_SECOND, 'status' => true]);
        } else {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => 'Not enough parameters', 'status' => false]);
        }

    }

    /**
     * @api {post} deleteimage  Delete main image
     * @apiVersion 2.0.0
     * @apiName deleteimage
     * @apiGroup Image
     *
     * @apiParam {string} token your unique token.
     * @apiParam {number} image_id Image id.
     * @apiParam {number} product_id Product id.
     *
     * @apiSuccess {Number} version  Current API version.
     * @apiSuccess {Boolean} status  true.
     *
     * @apiSuccessExample Success-Response:
     *     HTTP/1.1 200 OK
     *   {
     *       "status": true,
     *       "version": 2.0
     *   }
     *
     * @apiErrorExample Error-Response:
     *
     *     {
     *       "error" : "Not enough parameters",
     *       "version": 2.0,
     *       "Status" : false
     *     }
     *
     */
    if ( $mode == 'deleteimage' ) {
        $response = $_REQUEST;
        $error = fn_check_token();
        if ($error !== null) {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => $error, 'status' => false]);
        }
        if ( isset($response['image_id']) && !empty($response['image_id']) &&
            isset($response['product_id']) && !empty($response['product_id']) ) {
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

            fn_answer(['version' => $API_VERSION_SECOND, 'response' => $answer, 'status' => true]);
        } else {
            fn_answer(['version' => $API_VERSION_SECOND, 'error' => 'Not enough parameters', 'status' => false]);
        }

    }
}

function fn_check_token()
{
    if (!isset($_REQUEST['token']) || $_REQUEST['token'] == '') {
        $error = 'You need to be logged!';
    } else {
        $tokens = fn_get_all_tokens();
        if (count($tokens) > 0) {
            foreach ($tokens as $token) {
                if ($_REQUEST['token'] == $token['token']) {
                    $error = null;
                    break;
                } else {
                    $error = 'Token does not exist';
                }
            }
        } else {
            $error = 'You need to be logged!';
        }
    }

    return $error;
}