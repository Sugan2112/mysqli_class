<?php
require_once 'PHP class.php';

$db = new DB(true); //Если передан True, то включен debug

echo '<pre>';
/*Добавление строки*/
var_dump($db->add('products', ['name' => 'Коробка', 'price' => 9999.9999, 'count' => 3,]));

/*Добавление строки с параметром по-умолчанию*/
var_dump($db->add('products', ['name' => 'Коробка', 'price' => 999]));

/*Изменение строки с условием*/
var_dump($db->update('products', ['count' => 5,], 'count = 0'));

/*Изменение строки с иным условием*/
var_dump($db->update('products', ['price' => 750,], 'id = 1'));

/*Изменение строки работа с данными ячеек*/
var_dump($db->update('products', ['count' => [ 'count+' => '1',]]));
/*Изменение строки работа с данными ячеек с условием*/
var_dump($db->update('products', ['price' => [ 'price-' => '25',]], 'price = 750'));
var_dump($db->update('products', ['price' => [ 'price-' => '25',], 'count' => [ 'count+' => '1',]], 'price = 750'));

/*Удаление строки*/
var_dump($db->add('products', ['name' => 'Коробка', 'price' => 750]));
var_dump($db->delete('products', 'price=750'));

/*Выборка одной строки*/
var_dump($db->getOne('products'));
var_dump($db->getOne('products', 'name, price'));
var_dump($db->getOne('products', '*', ['count' => 3,]));
var_dump($db->getOne('products', '*', [], ['count' => 'max',]));

/*Выборка строк*/
var_dump($db->get('products'));
var_dump($db->get('products', 'name, price'));
var_dump($db->get('products', '*', ['count' => 3,]));
var_dump($db->get('products', '*', [], ['count' => 'max',]));

/*Кастомный запрос*/
var_dump($db->query("SELECT * FROM products WHERE id = ?", [1,], true));
var_dump($db->query("SELECT * FROM products WHERE name = ?", ['Коробка',], true));
var_dump($db->query("SELECT * FROM products WHERE id = 1"));
var_dump($db->query("SELECT * FROM products"));
echo '</pre>';

echo '<br><hr><br>';
echo 'DEBUG: <br>';
echo '<pre>';
var_dump($db->debug);
echo '</pre>';
