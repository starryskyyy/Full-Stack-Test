<?php

class User {

    // GENERAL
    public static function get_user($user_id) {
        
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email
            FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int)$row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email']
            ];
        } else {
            return [
                'id' => 0,
                'plot_id' => '',
                'first_name' => '',
                'last_name' => '',
                'phone' => 0,
                'email' => ''
            ];
        }
    }
    
    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }
    

    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) {
            $where[] = "(phone LIKE '%".$search."%' OR first_name LIKE '%".$search."%' OR email LIKE '%".$search."%')";
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
                        FROM users ".$where." ORDER BY CAST(plot_id AS SIGNED) ASC LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => beautify_phone_number($row['phone']),
                'email' => strtolower($row['email']),
                'last_login' => date('Y/m/d H:i:s', $row['last_login'])
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::get_user($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $plot_ids = isset($d['plot_id']) ? $d['plot_id'] : '';
        $plot_ids = implode(',', array_map('trim', explode(',', $plot_ids))); // Ensure clean formatting
        $first_name = isset($d['first_name']) && ucwords(strtolower($d['first_name'])) ? $d['first_name'] : '';
        $last_name = isset($d['last_name']) && ucwords(strtolower($d['last_name'])) ? $d['last_name'] : '';
        $phone = isset($d['phone']) ? phone_formatting($d['phone']) : 0;
        $email = isset($d['email']) ? strtolower($d['email']) : '';
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;


        // error (empty first_name)
        if (empty($first_name)) {
            return error_response(2003, 'First name is missing or in the wrong format.', ['first_name' => 'empty or invalid']);
        }

        // error (empty last_name)
        if (empty($last_name)) {
            return error_response(2004, 'Last name is missing or in the wrong format.', ['last_name' => 'empty or invalid']);
        }

        // error (empty phone)
        if (!$phone) {
            return error_response(2005, 'Phone number is missing or in the wrong format.', ['phone' => 'empty or invalid']);
        }

        // error (empty email)
        if (empty($email)) {
            return error_response(2006, 'Email is missing or in the wrong format.', ['email' => 'empty or invalid']);
        }

        // update
        try {
            if ($user_id) {
                $set = [];
                $set[] = "plot_id='".$plot_ids."'";
                $set[] = "first_name='".$first_name."'";
                $set[] = "last_name='".$last_name."'";
                $set[] = "phone='".$phone."'";
                $set[] = "email='".$email."'";
                $set = implode(", ", $set);

                DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;");
            } else {
                DB::query("INSERT INTO users (
                    plot_id,
                    first_name,
                    last_name,
                    phone,
                    email
                ) VALUES (
                    '".$plot_ids."',
                    '".$first_name."',
                    '".$last_name."',
                    '".phone_formatting($phone)."',
                    '".strtolower($email)."'
                );");
            }
            // output
            return User::users_fetch(['offset' => $offset]);
        } catch (Exception $e) {
            // error (database query)
            return error_response(2007, 'Error updating or inserting user data.', ['database' => $e->getMessage()]);
        }
    }
}
