<?php
/*
* Описание объекта для работы с базой данных (MySqli реализация)
*/
class DB
{
  protected $db;

  private bool $is_debug;
  public array $debug;

  //Данные для соединения с базой данных
  protected $db_conn = [
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'root',
    'database' => 'database',
    'port' => '3306',
    'charset' => 'utf8',
  ];

  //debug = true подключит обработку функции отладки
  function __construct($is_debug = false)
  {
    $this->debug = [];
    $this->is_debug = $is_debug;
    $this->db_connect();
  }

  function __destruct()
  {
    $this->db->close();
  }

  //Подключение к базе данных
  protected function db_connect()
  {
    mysqli_report(MYSQLI_REPORT_OFF);
    $this->db = @new mysqli($this->db_conn['hostname'], $this->db_conn['username'], $this->db_conn['password'], $this->db_conn['database'], $this->db_conn['port']);
    if ($this->db->connect_error) {
      die("Connection failed: " . $this->db->connect_error);
    }
    $this->db->set_charset($this->db_conn['charset']);
  }



  /*****************************************************************************
  * Методы
  *****************************************************************************/
  /*
  * Добавление строки, вернет номер добавленной строки
  *
  * $table - таблица.
  * $params - массив где ключ - это название столбца, а значение - это значение ячейки.
  * Пример:
  * $params = ['name' => 'Ivan', 'surname' => 'Ivanov']
  * Столбец name, значение Ivan и cтолбец surname, значение Ivanov
  * debug - запись данных в отладчик (false - не записывать, true - записать)
  */
  public function add(string $table, $params)
  {
    if (self::is_multi_array($params)) {
      die("DB object syntax error: In second argument of the \"add method\", the array must be not multidimensional");
    }
    extract(self::get_params('add', $params)); //Получение и систематизация параметров

    //Подготовка имен
    $names = implode(", ", $names);

    //Получение количества значений, расчет и запись меток
    $params_count = str_repeat("?,", count($params));
    $params_count = substr($params_count,0,-1);

    $query = "INSERT INTO `" . $table . "` (id," . $names . ") VALUES (NULL," . $params_count . ")";

    //Debug
    if ($this->is_debug) {
      $debug_time = microtime(true);
    }

    $qr_result = self::prepare_query('add', $query, $types, $data, $big_data); //Подготовка и выполнение запроса

    //Debug
    if ($this->is_debug) {
      self::db_debug($query, $params, $debug_time);
    }

    if (empty($qr_result->insert_id)) {
      $qr_result->close();
      return false;
    }
    $result = $qr_result->insert_id;
    $qr_result->close();
    return $result;
  }

  /*
  * Изменение строки
  * $table - таблица.
  * $where - строка условия выборки
  * $params - массив где ключ - это название столбца, а значение - это значение ячейки.
  * Пример:
  * $params = ['name' => 'Ivan', 'surname' => 'Ivanov']
  * Столбец name, значение Ivan и cтолбец surname, значение Ivanov
  * $params в этом методе поддерживает арифметические операции со столбцами
  * Пример:
  * $params = ['price' => ['price+' => '1']]
  * Изменить все ячейки всех строк столбца price на price+1
  * $params = ['price' => ['price+' => '1'], 'price < 750']
  * Изменить значение ячеек в столбце price на price+1, где price < 750
  * debug - запись данных в отладчик (false - не записывать, true - записать)
  */
  public function update(string $table, array $params, string $where = '')
  {
    extract(self::get_params('add', $params)); //Получение и систематизация параметров

    //Подготовка имен
    foreach ($names as $key => &$value) {
      if (strpos($value, "=") === false) {
        $value .= "=?";
      }
      else {
        $value .= "?";
      }
    }
    unset($value);

    $names = implode(",", $names);

    if (empty($where)) {
      $query = "UPDATE `" . $table . "` SET " . $names;
    }
    else {
      $query = "UPDATE `" . $table . "` SET " . $names . " WHERE " . $where ;
    }

    //Debug
    if ($this->is_debug) {
      $debug_time = microtime(true);
    }

    $qr_result = self::prepare_query('update', $query, $types, $data, $big_data); //Подготовка и выполнение запроса

    //Debug
    if ($this->is_debug) {
      self::db_debug($query, $params, $debug_time);
    }

    $result = $qr_result->affected_rows;
    $qr_result->close();
    return $result;
  }

  /*
  * Удаление строки (JOIN не реализован)
  */
  public function delete(string $table, string $where)
  {
    if (!empty($where)) {
      $query = "DELETE FROM `" . $table . "` WHERE " . $where ;
      //Debug
      if ($this->is_debug) {
        $debug_time = microtime(true);
      }
      if ($stmt = $this->db->prepare($query)) {
        if ($stmt->execute()) {
          if ($stmt->affected_rows === 0) {
            $stmt->close();
            return false;
          }
          $result =  $stmt->affected_rows;
          $stmt->close();
          //Debug
          if ($this->is_debug) {
            self::db_debug($query, [], $debug_time);
          }
          return $result;
        }
        die("DB object error: \"delete method\" " . $stmt->error);
      }
      die("DB object error: \"delete method\" " . $this->db->error);
    }
    die("DB object syntax error: In second argument of the \"delete method\", empty value");
  }

  /*
  * Выборка строк
  * cell_name - выборка конкретных столбцов
  * sort - сортировка по столбцу, значение min по возрастанию, max по убыванию
  *
  * В данном слечае реализовал подготовку запроса по where. (Это же можно внедрить и в другие методы, но я решил показать разные реализации)
  */
  public function get(string $table, string $cell_names = '*', array $where = [], array $sort = ['id' => 'min'])
  {
    return self::select($table, $cell_names, $sort, $where, 'getAll');
  }

  /*
  * Выборка строки
  * cell_name - выборка конкретных столбцов
  * sort - сортировка по столбцу, значение min по возрастанию, max по убыванию
  *
  */
  public function getOne(string $table, string $cell_names = '*', array $where = [], array $sort = ['id' => 'min'])
  {
    return self::select($table, $cell_names, $sort, $where, 'getOne');
  }


  /*
  * Select функция
  */
  private function select(string $table, string $cell_names, array $sort, array $where, $type)
  {
    $cell_names = htmlspecialchars($cell_names);

    switch ($type) {
      case 'getOne':
        $type = "LIMIT 1";
        break;

      case 'getAll':
        $type = "";
        break;

      default:
        $type = "";
        break;
    }

    switch ($sort[array_key_first($sort)]) {
      case 'min':
        $sort[array_key_first($sort)] = 'ASC';
        break;

      case 'max':
        $sort[array_key_first($sort)] = 'DESC';
        break;

      default:
        $sort[array_key_first($sort)] = 'ASC';
        break;
    }

    if (!empty($where)) {
      extract(self::get_params('add', $where)); //Получение и систематизация параметров

      //Подготовка имен
      foreach ($names as $key => &$value) {
        if (strpos($value, "=") === false) {
          $value .= "=?";
        }
        else {
          $value .= "?";
        }
      }
      unset($value);
      $names = implode(",", $names);
      $query = "SELECT " . $cell_names . " FROM " . $table . " WHERE " . $names . " ORDER BY " . array_key_first($sort) . " " . $sort[array_key_first($sort)] . " " . $type;

      //Debug
      if ($this->is_debug) {
        $debug_time = microtime(true);
      }

      $qr_result = self::prepare_query('get', $query, $types, $data, $big_data); //Подготовка и выполнение запроса
      $result = $qr_result->get_result();

      //Debug
      if ($this->is_debug) {
        self::db_debug($query, $where, $debug_time);
      }
    }
    else {
      $query = "SELECT " . $cell_names . " FROM " . $table . " ORDER BY " . array_key_first($sort) . " " . $sort[array_key_first($sort)] . " " . $type;

      //Debug
      if ($this->is_debug) {
        $debug_time = microtime(true);
      }

      $result = $this->db->query($query);

      //Debug
      if ($this->is_debug) {
        self::db_debug($query, [], $debug_time);
      }
    }

    $result_data = [];

    foreach ($result as $qr_data) {
      $result_data[] = $qr_data;
    }

    return $result_data;
  }

  /*
  * Метод для выполнения кастомного запроса
  * query_type если true, то подготовить запрос
  * Вернет объект result
  */
  public function query(string $query, array $params = [], bool $query_type = false)
  {
    //Подготовленный запрос
    if ($query_type) {
      $types = '';
      $big_data = [];

      foreach ($params as $key => &$value) { //Дль демонстрации работы ссылок
        switch (gettype($value)) {
          case 'integer':
            $types .= 'i';
            break;

          case 'boolean':
            $types .= 'i';
            break;

          case 'double':
            $types .= 'd';
            break;

          //Предпологаем, что строки и данные вложеных массивов - превышают max. allowed packet size
          case 'string':
            $types .= 'b';
            $big_data[$key] = $value;
            $value = NULL;
            break;
        }
      }
      unset($value);

      //Подготовка и выполнение запроса
      //Debug
      if ($this->is_debug) {
        $debug_time = microtime(true);
      }

      $qr_result = self::prepare_query('query', $query, $types, $params, $big_data);
      $result = $qr_result->get_result();
      $qr_result->close();

      //Debug
      if ($this->is_debug) {
        self::db_debug($query, $params, $debug_time);
      }

      return $result;
    }
    else {
      //Debug
      if ($this->is_debug) {
        $debug_time = microtime(true);
      }

      //Не подготовленный запрос
      $result = $this->db->query($query);

      //Debug
      if ($this->is_debug) {
        self::db_debug($query, $params, $debug_time);
      }

      return $result;
    }
  }


  /*****************************************************************************
  * Отладчик
  *****************************************************************************/
  private function db_debug($query, $params, $debug_time)
  {
    $debug_time = microtime(true) - $debug_time;
    $debug_query = $query;
    foreach ($params as $value) {
      if (is_array($value)) {
        $value = $value[array_key_first($value)];
      }
      $debug_query = substr( $debug_query, 0, strpos( $debug_query, "?")) . $value . substr( $debug_query, strpos( $debug_query, "?") + strlen("?"));
    }

    $this->debug[] = [
      'query' => $debug_query,
      'time' => round($debug_time, 4, PHP_ROUND_HALF_UP),
    ];
  }


  /*****************************************************************************
  * Функции
  *****************************************************************************/

  /*
  * Получение названий столбцов, типа данных и значение
  */
  private function get_params(string $method, array $params) {
    if (is_array($params) && !empty($params)) {
      $names = []; //Названия столбцов
      $types = ''; //Типы данных
      $data = []; //Данные
      $big_data = []; //Большие данные

      //Получение и структуризация данных из массива $params, определение типов данных
      foreach ($params as $key => $value) {
        if (is_array($value) && count($value) !== 1) {
          die("DB object syntax error: In second argument of the \"" . $method . " method\", more than two values in a nested array");
        }

        switch (gettype($value)) {
          case 'integer':
            $types .= 'i';
            $data[] = $value;
            $names[] = $key;
            break;

          case 'boolean':
            $types .= 'i';
            $data[] = $value;
            $names[] = $key;
            break;

          case 'double':
            $types .= 'd';
            $data[] = $value;
            $names[] = $key;
            break;

          //Предпологаем, что строки и данные вложеных массивов - превышают max. allowed packet size
          case 'string':
            $types .= 'b';
            $data[] = NULL;
            $names[] = $key;
            $big_data[array_key_last($data)] = $value;
            break;

          case 'array':
            $types .= 'b';
            $data[] = NULL;
            $names[] = preg_replace('%[^a-zа-я\d]%i', '', $key) . ' = ' . array_key_first($value);
            $big_data[array_key_last($data)] = $value[array_key_first($value)];
            break;

          default:
            die("DB object error: Invalid data type on second argument of the \"" . $method . " method\" in " . $key);
            break;
        }
      }

      $result = [
        'names' => $names,
        'data' => $data,
        'big_data' => $big_data,
        'types' => $types,
      ];

      return $result;
    }
    die("DB object syntax error: The second argument of the \"" . $method . " method\" must be an array and not empty");
  }

  /*
  *Подготовка запроса
  */
  private function prepare_query(string $method, string $query, string $types, array $data, array $big_data) {
    if ($stmt = $this->db->prepare($query)) {
      if ($stmt->bind_param($types, ...$data)) {
        //Обработка данных при превышении max. allowed packet size
        foreach ($big_data as $key => $value) {
          $stmt->send_long_data($key, $value);
        }
        if ($stmt->execute()) {
          return $stmt;
        }
      }
      die("DB object error in \"" . $method . " method\": " . $stmt->error);
    }
    die("DB object error in \"" . $method . " method\": " . $this->db->error);
  }

  /*
  * Проверка на многомерный массив
  */
  private function is_multi_array($array) {
    if (count($array) === count($array, COUNT_RECURSIVE)) {
      return false;
    }
    return true;
  }
}
