<?php

class Order {
    
    public $number;
    public $cart;
    public $shipping;
    public $customer;
    public $stripe;
    public $ipAddress;
    public $created;
    public $updated;
    public $email;
    public $status;
    public $id;

    public function __construct(
        $number='',
        $cart=[],
        $shipping=[],
        $customer=[],
        $stripe=[],
        $ipAddress='',
        $created='',
        $updated='',
        $email='',
        $status='',
        $id=''
    )
    {
        $this->number = $number;
        $this->cart = $cart;
        $this->shipping = $shipping;
        $this->customer = $customer;
        $this->stripe = $stripe;
        $this->ipAddress = $ipAddress;
        $this->created = $created;
        $this->updated = $updated;
        $this->email = $email;
        $this->status = $status;
        $this->id = $id;
    }
    
    // Create Order Number
    public function generateOrderNumber()
    {
        return uniqid();
    }
    public function createOrderNumber()
    {
        return uniqid();
    }
    public function addItemsToCart($items){
        $array = [];
        foreach($items as $item) $array[] = $item;
        $this->cart = $array;

    }
    public function updateOrderCart(){
        return edit('Orders', $this->number, ['Cart'=>json_encode($this->cart)]);
    }

    public function save($type='existing'){
        if($type == 'new'){
            return add('Orders', [
                'Number' => $this->number,
                'Cart' => json_encode($this->cart),
                'Shipping' => json_encode($this->shipping),
                'Customer' => json_encode($this->customer),
                'Stripe' => json_encode($this->stripe),
                'IPAddress' => $this->ipAddress,
                'Email' => $this->email,
                'Status' => $this->status,
                'Created' => date('Y-m-d H:i:s')
            ]);
        } else {
            return edit('Orders', $this->id, [
                'Cart' => json_encode($this->cart),
                'Shipping' => json_encode($this->shipping),
                'Customer' => json_encode($this->customer),
                'Stripe' => json_encode($this->stripe),
                'Updated' => date('Y-m-d H:i:s'),
                'Status' => $this->status
            ]);
        }

    }

}