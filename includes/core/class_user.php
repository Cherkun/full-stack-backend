<?php

class User {

    // GENERAL

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


    public static function user_info_by_id($user_id) {
    $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
            FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
    if ($row = DB::fetch_row($q)) {
        return [
            'id' => (int) $row['user_id'],
            'plot_id' => $row['plot_id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'plots' => $row['plot_id'] ? Plot::plots_list_users($row['plot_id']) : [],
            'last_login' => date('Y/m/d', $row['last_login'])
        ];
    } else {
        return [
            'id' => 0,
            'first_name' => '',
            'plot_id' => '',
            'last_name' => '',
            'phone' => '',
            'email' => '',
            'plots' => [],
            'last_login' => 0
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
        if ($search) $where[] = "first_name LIKE '%".$search."%' or email LIKE '%".$search."%' or last_name LIKE '%".$search."%' or phone LIKE '%".$search."%'";
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
            FROM users ".$where." ORDER BY user_id+0 LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'user_id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'plots' => $row['plot_id'] ? Plot::plots_list_users($row['plot_id']) : [],
                'last_login' => date('Y/m/d', $row['last_login'])
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

    // ACTIONS

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info_by_id($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $plot_id = isset($d['plot_id']) && trim($d['plot_id']) ? trim($d['plot_id']) : '';
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : false;
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : false;
        $email = isset($d['email']) && trim($d['email']) ? strtolower(trim($d['email'])) : false;
        $phone = isset($d['phone']) && trim(preg_replace('~\D+~', '', $d['phone'])) ? preg_replace('~\D+~', '', $d['phone']) : false;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // update
        if ($first_name && $last_name && $email && $phone) {
            if ($user_id) {
                $set = [];
                $set[] = "plot_id='" . $plot_id . "'";
                $set[] = "first_name='" . $first_name . "'";
                $set[] = "last_name='" . $last_name . "'";
                $set[] = "email='" . $email . "'";
                $set[] = "phone='" . $phone . "'";
                $set = implode(", ", $set);
                DB::query("UPDATE users SET " . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die (DB::error());
            } else {
                DB::query("INSERT INTO users (
                plot_id,
                first_name,
                last_name,
                email,
                phone
            ) VALUES (
                '" . $plot_id . "',
                '" . $first_name . "',
                '" . $last_name . "',
                '" . $email . "',
                '" . $phone . "'
            );") or die (DB::error());
            }
        } else {
            return false;
        }
        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function delete($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        DB::query("DELETE FROM users WHERE user_id='" . $user_id . "' LIMIT 1;") or die (DB::error());
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // output
        return User::users_fetch(['offset' => $offset]);
    }

}
