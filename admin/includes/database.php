<?php
require_once('constants.php');

class Database
{
    protected $conn;

    public function __construct()
    {
        $this->conn = mysqli_connect(LOCALHOST, USERNAME, PASSWORD, DBNAME);

        if (!$this->conn) {
            die("Database connection failed: " . mysqli_connect_error());
        }
    }

    // ✅ SAFE QUERY (Prepared Statements)
    public function query($sql, $params = [])
    {
        $stmt = mysqli_prepare($this->conn, $sql);

        if (!is_array($params)) {
            $params = []; // prevent crash
        }

        if (!empty($params)) {
            $types = str_repeat("s", count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }

    // ✅ Check if a record exists
    public function recordExists($table, $column, $value)
    {
        $sql = "SELECT 1 FROM $table WHERE $column = ? LIMIT 1";
        $result = $this->query($sql, [$value]);
        return $this->count($result) > 0;
    }

    // ✅ INSERT with proper error handling
    public function insertSafe($table, $data)
    {
        try {
            $columns = implode(",", array_keys($data));
            $placeholders = implode(",", array_fill(0, count($data), "?"));

            $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
            $stmt = mysqli_prepare($this->conn, $sql);

            if (!$stmt) {
                return ['success' => false, 'error' => mysqli_error($this->conn)];
            }

            $types = str_repeat("s", count($data));
            mysqli_stmt_bind_param($stmt, $types, ...array_values($data));

            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_stmt_error($stmt);
                // Handle unique constraint violation
                if (strpos($error, 'Duplicate entry') !== false) {
                    return ['success' => false, 'error' => 'This SKU already exists. Please use a unique SKU.', 'type' => 'duplicate'];
                }
                return ['success' => false, 'error' => $error];
            }

            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected > 0) {
                return ['success' => true, 'insert_id' => mysqli_insert_id($this->conn)];
            } else {
                return ['success' => false, 'error' => 'Failed to insert record'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ✅ UPDATE with proper error handling
    public function updateSafe($table, $data, $where, $whereParams = [])
    {
        try {
            $set = "";
            foreach ($data as $key => $value) {
                $set .= "$key=?,";
            }
            $set = rtrim($set, ",");

            $sql = "UPDATE $table SET $set WHERE $where";
            $stmt = mysqli_prepare($this->conn, $sql);

            if (!$stmt) {
                return ['success' => false, 'error' => mysqli_error($this->conn)];
            }

            $allParams = array_merge(array_values($data), $whereParams);
            $types = str_repeat("s", count($allParams));
            mysqli_stmt_bind_param($stmt, $types, ...$allParams);

            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_stmt_error($stmt);
                if (strpos($error, 'Duplicate entry') !== false) {
                    return ['success' => false, 'error' => 'This SKU already exists. Please use a unique SKU.', 'type' => 'duplicate'];
                }
                return ['success' => false, 'error' => $error];
            }

            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            return ['success' => true, 'affected_rows' => $affected];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ✅ DELETE with proper error handling
    public function deleteSafe($table, $where, $params = [])
    {
        try {
            $sql = "DELETE FROM $table WHERE $where";
            $stmt = mysqli_prepare($this->conn, $sql);

            if (!$stmt) {
                return ['success' => false, 'error' => mysqli_error($this->conn)];
            }

            if (!empty($params)) {
                $types = str_repeat("s", count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }

            if (!mysqli_stmt_execute($stmt)) {
                return ['success' => false, 'error' => mysqli_stmt_error($stmt)];
            }

            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            return ['success' => true, 'affected_rows' => $affected];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Fetch all rows
    public function fetchAll($result)
    {
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    // Fetch single row
    public function fetch($result)
    {
        return mysqli_fetch_assoc($result);
    }

    // Count rows
    public function count($result)
    {
        return mysqli_num_rows($result);
    }

    // Insert (SAFE)
    public function insert($table, $data)
    {
        $columns = implode(",", array_keys($data));
        $placeholders = implode(",", array_fill(0, count($data), "?"));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        return $this->query($sql, array_values($data));
    }

    // Update (SAFE)
    public function update($table, $data, $where, $whereParams = [])
    {
        $set = "";
        foreach ($data as $key => $value) {
            $set .= "$key=?,";
        }
        $set = rtrim($set, ",");

        $sql = "UPDATE $table SET $set WHERE $where";
        return $this->query($sql, array_merge(array_values($data), $whereParams));
    }

    // Delete (SAFE)
    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }

    // Select
    public function select($table, $where = "", $params = [], $order = "")
    {
        $sql = "SELECT * FROM $table";

        if ($where) {
            $sql .= " WHERE $where";
        }

        if ($order) {
            $sql .= " ORDER BY $order";
        }

        return $this->query($sql, $params);
    }

    // Search medicines
    public function searchMedicine($keyword)
    {
        $sql = "SELECT * FROM medicines WHERE name LIKE ?";
        return $this->query($sql, ["%$keyword%"]);
    }

    // Low stock check
    public function getLowStock()
    {
        $sql = "SELECT * FROM inventory WHERE quantity <= reorder_level";
        return $this->query($sql);
    }

    // Expiry alerts
    public function getExpiringMedicines()
    {
        $sql = "SELECT * FROM inventory WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        return $this->query($sql);
    }
}
?>