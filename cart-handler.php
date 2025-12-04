<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Please login to continue'], 401);
}

$db = new Database();
$action = sanitize($_POST['action'] ?? '');
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'add':
        $productId = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($productId <= 0 || $quantity <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid product or quantity']);
        }
        
        // Check if product exists and is active
        $product = $db->query("SELECT id, name, price FROM products WHERE id = ? AND status = 'active'", [$productId])->fetch();
        
        if (!$product) {
            jsonResponse(['success' => false, 'message' => 'Product not found']);
        }
        
        // Check if item already in cart
        $existingItem = $db->query("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?", [$userId, $productId])->fetch();
        
        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem['quantity'] + $quantity;
            $db->query("UPDATE cart SET quantity = ? WHERE id = ?", [$newQuantity, $existingItem['id']]);
        } else {
            // Add new item
            $db->query("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)", [$userId, $productId, $quantity]);
        }
        
        // Update cart count in session
        $cartCount = $db->query("SELECT COUNT(*) as count FROM cart WHERE user_id = ?", [$userId])->fetch()['count'];
        $_SESSION['cart_count'] = $cartCount;
        
        jsonResponse([
            'success' => true, 
            'message' => 'Product added to cart',
            'cart_count' => $cartCount
        ]);
        break;
        
    case 'update':
        $cartId = intval($_POST['cart_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($cartId <= 0 || $quantity <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid cart item or quantity']);
        }
        
        // Verify cart item belongs to user
        $cartItem = $db->query("SELECT id FROM cart WHERE id = ? AND user_id = ?", [$cartId, $userId])->fetch();
        
        if (!$cartItem) {
            jsonResponse(['success' => false, 'message' => 'Cart item not found']);
        }
        
        // Update quantity
        $db->query("UPDATE cart SET quantity = ? WHERE id = ?", [$quantity, $cartId]);
        
        // Get updated cart totals
        $cartTotals = $db->query("
            SELECT SUM(c.quantity * p.price) as total, COUNT(*) as items
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?
        ", [$userId])->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Cart updated',
            'total' => $cartTotals['total'],
            'items' => $cartTotals['items']
        ]);
        break;
        
    case 'remove':
        $cartId = intval($_POST['cart_id'] ?? 0);
        
        if ($cartId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid cart item']);
        }
        
        // Verify and remove cart item
        $result = $db->query("DELETE FROM cart WHERE id = ? AND user_id = ?", [$cartId, $userId]);
        
        if ($result->rowCount() > 0) {
            // Update cart count in session
            $cartCount = $db->query("SELECT COUNT(*) as count FROM cart WHERE user_id = ?", [$userId])->fetch()['count'];
            $_SESSION['cart_count'] = $cartCount;
            
            // Get updated cart totals
            $cartTotals = $db->query("
                SELECT SUM(c.quantity * p.price) as total, COUNT(*) as items
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
            ", [$userId])->fetch();
            
            jsonResponse([
                'success' => true,
                'message' => 'Item removed from cart',
                'cart_count' => $cartCount,
                'total' => $cartTotals['total'],
                'items' => $cartTotals['items']
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Cart item not found']);
        }
        break;
        
    case 'clear':
        $result = $db->query("DELETE FROM cart WHERE user_id = ?", [$userId]);
        
        if ($result->rowCount() > 0) {
            $_SESSION['cart_count'] = 0;
            jsonResponse(['success' => true, 'message' => 'Cart cleared', 'cart_count' => 0]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Cart is already empty']);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action']);
}
?>
