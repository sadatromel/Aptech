<?php

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Core\Container;
use Core\Database;

try {
    $db = Container::get(Database::class);
    echo "=== SEEDING RMG MASTER & EMPLOYEE DATA ===\n";

    // 1. Departments
    $depts = [
        ['code' => 'ADMIN', 'name' => 'Admin'],
        ['code' => 'HR', 'name' => 'HR'],
        ['code' => 'SEW', 'name' => 'Sewing'],
        ['code' => 'CUT', 'name' => 'Cutting'],
        ['code' => 'WASH', 'name' => 'Washing'],
        ['code' => 'PACK', 'name' => 'Packing'],
        ['code' => 'QC', 'name' => 'Quality Control'],
        ['code' => 'FIN', 'name' => 'Finance']
    ];

    $deptMap = [];
    foreach ($depts as $d) {
        $existing = $db->get('departments', '*', ['department_name' => $d['name']]);
        if ($existing) {
            $deptMap[$d['name']] = (int)$existing['department_id'];
        } else {
            $id = $db->insert('departments', [
                'department_code' => $d['code'],
                'department_name' => $d['name'],
                'status' => 'active'
            ]);
            $deptMap[$d['name']] = $id;
        }
    }

    // 2. Sections
    $sections = [
        ['dept' => 'Admin', 'name' => 'MIS'],
        ['dept' => 'HR', 'name' => 'HR & Welfare'],
        ['dept' => 'Sewing', 'name' => 'Sewing Section A'],
        ['dept' => 'Cutting', 'name' => 'Cutting Prep'],
        ['dept' => 'Washing', 'name' => 'Washing Unit 1'],
        ['dept' => 'Packing', 'name' => 'Poly & Carton Packing'],
        ['dept' => 'Quality Control', 'name' => 'Inline QC']
    ];

    $secMap = [];
    foreach ($sections as $s) {
        $dId = $deptMap[$s['dept']] ?? 1;
        $existing = $db->get('sections', '*', ['section_name' => $s['name']]);
        if ($existing) {
            $secMap[$s['name']] = (int)$existing['section_id'];
        } else {
            $id = $db->insert('sections', [
                'department_id' => $dId,
                'section_name' => $s['name'],
                'status' => 'active'
            ]);
            $secMap[$s['name']] = $id;
        }
    }

    // 3. Positions
    $positions = [
        'Executive', 'Jr. Officer MIS', 'Senior Officer', 'MIS Officer', 
        'Sr. Manager', 'Trainee MIS', 'Jr. Officer', 'Asst. Received Man', 
        'Asst.', 'Sewing Operator', 'Quality Inspector', 'Line Supervisor'
    ];

    $posMap = [];
    foreach ($positions as $posName) {
        $existing = $db->get('positions', '*', ['position_name' => $posName]);
        if ($existing) {
            $posMap[$posName] = (int)$existing['position_id'];
        } else {
            $id = $db->insert('positions', [
                'position_name' => $posName,
                'status' => 'active'
            ]);
            $posMap[$posName] = $id;
        }
    }

    // 4. Production Lines
    $lines = ['MIS_No Line', 'MIS Worker', 'Line 01', 'Line 02', 'Cutting Line 1'];
    $lineMap = [];
    foreach ($lines as $lineName) {
        $existing = $db->get('production_lines', '*', ['line_name' => $lineName]);
        if ($existing) {
            $lineMap[$lineName] = (int)$existing['production_line_id'];
        } else {
            $id = $db->insert('production_lines', [
                'line_name' => $lineName,
                'line_code' => strtoupper(substr(str_replace(' ', '', $lineName), 0, 6)),
                'line_type' => 'traditional',
                'status' => 'active',
                'capacity_per_day' => 800
            ]);
            $lineMap[$lineName] = $id;
        }
    }

    // 5. Seed Real Employees
    $employeesData = [
        ['code' => '000001233', 'first' => 'Md. Sazzad', 'last' => 'Hossain', 'pos' => 'Executive', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2019-07-02'],
        ['code' => '000002191', 'first' => 'Md. Rasel', 'last' => 'Mondol', 'pos' => 'Jr. Officer MIS', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2020-10-18'],
        ['code' => '000002369', 'first' => 'Md. Lutfor', 'last' => 'Rahman', 'pos' => 'Jr. Officer MIS', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2021-01-04'],
        ['code' => '000007183', 'first' => 'Shahnewaz', 'last' => 'Sina', 'pos' => 'Senior Officer', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2025-04-15'],
        ['code' => '000007190', 'first' => 'Md. Masud', 'last' => 'Rana', 'pos' => 'MIS Officer', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2025-04-15'],
        ['code' => '000007195', 'first' => 'Md.', 'last' => 'Obaidulla', 'pos' => 'MIS Officer', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2025-04-15'],
        ['code' => '000007224', 'first' => 'Abu Sadat', 'last' => 'Al Chisti', 'pos' => 'Sr. Manager', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2025-05-05'],
        ['code' => '000007246', 'first' => 'Sakibul Hassan', 'last' => 'Sohug', 'pos' => 'Trainee MIS', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2025-06-21'],
        ['code' => '081001360', 'first' => 'Fahim Muntasir', 'last' => 'Bappi', 'pos' => 'Jr. Officer', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2024-11-16'],
        ['code' => '091004451', 'first' => 'Md. Alomgir', 'last' => 'Hossain', 'pos' => 'Jr. Officer MIS', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS_No Line', 'hire' => '2022-04-11'],
        ['code' => '000006568', 'first' => 'Md. Zosim', 'last' => 'Uddin', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2023-05-10'],
        ['code' => '000006798', 'first' => 'Md. Al', 'last' => 'Amin', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2024-01-03'],
        ['code' => '000007318', 'first' => 'Md. Rabbi', 'last' => 'Mahin', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2025-08-19'],
        ['code' => '000007368', 'first' => 'Md. Rashedul', 'last' => 'Hoq', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2025-09-20'],
        ['code' => '000007398', 'first' => 'Md. Mostofa', 'last' => 'Kamal', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2025-10-18'],
        ['code' => '000007399', 'first' => 'Md. Ahsanul', 'last' => 'Haque', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2025-10-18'],
        ['code' => '000007422', 'first' => 'Md. Foysal Hossen', 'last' => 'Roni', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2025-11-06'],
        ['code' => '000007559', 'first' => 'Md. Masud', 'last' => 'Rana', 'pos' => 'Asst.', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2026-06-20'],
        ['code' => '000007560', 'first' => 'Md. Arafat', 'last' => 'Khan', 'pos' => 'Asst. Received Man', 'dept' => 'Admin', 'sec' => 'MIS', 'line' => 'MIS Worker', 'hire' => '2026-06-20']
    ];

    $empSeededCount = 0;
    foreach ($employeesData as $emp) {
        $existing = $db->get('employees', '*', ['employee_code' => $emp['code']]);
        if (!$existing) {
            $db->insert('employees', [
                'employee_code' => $emp['code'],
                'first_name' => $emp['first'],
                'last_name' => $emp['last'],
                'department_id' => $deptMap[$emp['dept']] ?? 1,
                'section_id' => $secMap[$emp['sec']] ?? 1,
                'position_id' => $posMap[$emp['pos']] ?? 1,
                'line_id' => $lineMap[$emp['line']] ?? 1,
                'hire_date' => $emp['hire'],
                'employment_type' => str_contains($emp['line'], 'Worker') ? 'Worker' : 'Staff',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $empSeededCount++;
        }
    }

    echo "Seeded $empSeededCount new employees successfully!\n";
    echo "Total employees in DB: " . $db->count('employees') . "\n";

} catch (\Throwable $e) {
    echo "Seeding error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
