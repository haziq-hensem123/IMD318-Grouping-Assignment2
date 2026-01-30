<?php
// api.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$orderFile = 'orders.json';
$billFile = 'bills.json';
$tableFile = 'tables.json';

if (!file_exists($orderFile)) file_put_contents($orderFile, '[]');
if (!file_exists($billFile)) file_put_contents($billFile, '[]');
if (!file_exists($tableFile)) {
    // table created 6 meja
    $tables = [];
    for($i=1; $i<=6; $i++) { $tables[$i] = ['status' => 'free', 'updated' => time()]; }
    file_put_contents($tableFile, json_encode($tables));
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

// chef punya button
if ($action === 'reset_all') {
    // reset meja
    $tables = [];
    for($i=1; $i<=6; $i++) { $tables[$i] = ['status' => 'free', 'updated' => time()]; }
    file_put_contents($tableFile, json_encode($tables));
    
    // clear sushi and bills
    file_put_contents($orderFile, '[]');
    file_put_contents($billFile, '[]');

    echo json_encode(["status" => "reset"]);
    exit;
}

// table exclusivity logic

if ($action === 'get_tables') {
    echo file_get_contents($tableFile);
    exit;
}

if ($action === 'occupy_table') {
    $id = $input['table'];
    $tables = json_decode(file_get_contents($tableFile), true);

    // rule table occupied
    if (isset($tables[$id]) && $tables[$id]['status'] === 'occupied') {
        echo json_encode(["status" => "fail", "message" => "Table Taken"]);
    } else {
        $tables[$id]['status'] = 'occupied';
        $tables[$id]['updated'] = time();
        file_put_contents($tableFile, json_encode($tables));
        echo json_encode(["status" => "success"]);
    }
    exit;
}

if ($action === 'free_table') {
    $id = $_GET['table']; // make sure it gets the table ID
    $tables = json_decode(file_get_contents($tableFile), true);
    
    if (isset($tables[$id])) {
        $tables[$id]['status'] = 'free';
        $tables[$id]['updated'] = time();
        file_put_contents($tableFile, json_encode($tables));
    }
    
    echo json_encode(["status" => "success"]);
    exit;
}

// sushi belt logic

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_sushi') {
    $input['id'] = uniqid();
    $input['timestamp'] = time(); // for tracking
    $orders = json_decode(file_get_contents($orderFile), true);
    $orders[] = $input;
    file_put_contents($orderFile, json_encode($orders));
    echo json_encode(["status" => "success"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'eat_sushi') {
    $plateId = $input['id'];
    $tableId = $input['table'];
    $orders = json_decode(file_get_contents($orderFile), true);
    
    // check if plate is still exist
    $foundIndex = -1;
    $eatenItem = null;
    foreach ($orders as $index => $item) {
        if ($item['id'] === $plateId) { $foundIndex = $index; $eatenItem = $item; break; }
    }

    if ($foundIndex > -1) {
        // if taken remove from belt
        array_splice($orders, $foundIndex, 1);
        file_put_contents($orderFile, json_encode($orders));
        
        // it will
        $bills = json_decode(file_get_contents($billFile), true);
        $bills[] = ["table" => $tableId, "name" => $eatenItem['name'], "price" => $eatenItem['price']];
        file_put_contents($billFile, json_encode($bills));
        
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "fail"]);
    }
    exit;
}

// menu logic untuk menu.html

if ($action === 'order_from_menu') {
    // JANGAN KASSAU
    $tableId = $input['table'];
    $itemName = $input['name'];
    $itemPrice = $input['price'];

    // add bills pergi bills.json
    $bills = json_decode(file_get_contents($billFile), true);
    $bills[] = [
        "table" => $tableId, 
        "name" => $itemName, 
        "price" => $itemPrice
    ];
    file_put_contents($billFile, json_encode($bills));

    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'get_belt') { echo file_get_contents($orderFile); exit; }

// logic billing

if ($action === 'get_bills') {
    echo file_get_contents($billFile);
    exit;
}

if ($action === 'clear_bill') {
    // Accept ID dari POST or GET
    $id = $input['table'] ?? $_GET['table'] ?? null;
    
    if ($id) {
        $bills = json_decode(file_get_contents($billFile), true);
        $newBills = [];

        foreach ($bills as $b) { 
  
            if ($b['table'] != $id) {
                $newBills[] = $b; 
            }
        }
        file_put_contents($billFile, json_encode($newBills));
        echo json_encode(["status" => "cleared"]);
    } else {
        echo json_encode(["status" => "error", "message" => "No ID provided"]);
    }
    exit;
}
?>