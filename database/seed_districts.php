<?php
/**
 * Database Seeder Script for 64 Districts and Sub-Districts of Bangladesh (with Bangla Names)
 */

$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Starting district database seeding with Bangla names...\n";

    // 1. Ensure Bangladesh exists in countries table
    $stmt = $pdo->prepare("SELECT country_id FROM countries WHERE country_name LIKE 'Bangladesh' OR country_code = 'BD' LIMIT 1");
    $stmt->execute();
    $country = $stmt->fetch();

    if ($country) {
        $countryId = (int)$country['country_id'];
    } else {
        $insertCountry = $pdo->prepare("INSERT INTO countries (country_name, country_code, region, status) VALUES ('Bangladesh', 'BD', 'Asia', 'active')");
        $insertCountry->execute();
        $countryId = (int)$pdo->lastInsertId();
    }

    // 2. Complete List of 64 Districts of Bangladesh with Bangla Names
    $districts = [
        // Dhaka Division
        ['district_name' => 'Dhaka', 'district_name_bn' => 'ঢাকা', 'district_code' => 'DHK', 'division_state' => 'Dhaka'],
        ['district_name' => 'Gazipur', 'district_name_bn' => 'গাজীপুর', 'district_code' => 'GZP', 'division_state' => 'Dhaka'],
        ['district_name' => 'Narayanganj', 'district_name_bn' => 'নারায়নগঞ্জ', 'district_code' => 'NRG', 'division_state' => 'Dhaka'],
        ['district_name' => 'Tangail', 'district_name_bn' => 'টাঙ্গাইল', 'district_code' => 'TNG', 'division_state' => 'Dhaka'],
        ['district_name' => 'Narsingdi', 'district_name_bn' => 'নরসিংদী', 'district_code' => 'NRD', 'division_state' => 'Dhaka'],
        ['district_name' => 'Faridpur', 'district_name_bn' => 'ফরিদপুর', 'district_code' => 'FRP', 'division_state' => 'Dhaka'],
        ['district_name' => 'Manikganj', 'district_name_bn' => 'মানিকগঞ্জ', 'district_code' => 'MNK', 'division_state' => 'Dhaka'],
        ['district_name' => 'Munshiganj', 'district_name_bn' => 'মুন্সিগঞ্জ', 'district_code' => 'MNS', 'division_state' => 'Dhaka'],
        ['district_name' => 'Rajbari', 'district_name_bn' => 'রাজবাড়ী', 'district_code' => 'RJB', 'division_state' => 'Dhaka'],
        ['district_name' => 'Gopalganj', 'district_name_bn' => 'গোপালগঞ্জ', 'district_code' => 'GPL', 'division_state' => 'Dhaka'],
        ['district_name' => 'Madaripur', 'district_name_bn' => 'মাদারীপুর', 'district_code' => 'MDP', 'division_state' => 'Dhaka'],
        ['district_name' => 'Shariatpur', 'district_name_bn' => 'শরীয়তপুর', 'district_code' => 'SHR', 'division_state' => 'Dhaka'],
        ['district_name' => 'Kishoreganj', 'district_name_bn' => 'কিশোরগঞ্জ', 'district_code' => 'KSG', 'division_state' => 'Dhaka'],

        // Chattogram Division
        ['district_name' => 'Chattogram', 'district_name_bn' => 'চট্টগ্রাম', 'district_code' => 'CTG', 'division_state' => 'Chattogram'],
        ['district_name' => "Cox's Bazar", 'district_name_bn' => 'কক্সবাজার', 'district_code' => 'CXB', 'division_state' => 'Chattogram'],
        ['district_name' => 'Cumilla', 'district_name_bn' => 'কুমিল্লা', 'district_code' => 'CML', 'division_state' => 'Chattogram'],
        ['district_name' => 'Feni', 'district_name_bn' => 'ফেনী', 'district_code' => 'FNI', 'division_state' => 'Chattogram'],
        ['district_name' => 'Noakhali', 'district_name_bn' => 'নোয়াখালী', 'district_code' => 'NKL', 'division_state' => 'Chattogram'],
        ['district_name' => 'Lakshmipur', 'district_name_bn' => 'লক্ষ্মীপুর', 'district_code' => 'LKP', 'division_state' => 'Chattogram'],
        ['district_name' => 'Brahmanbaria', 'district_name_bn' => 'ব্রাহ্মণবাড়িয়া', 'district_code' => 'BMB', 'division_state' => 'Chattogram'],
        ['district_name' => 'Chandpur', 'district_name_bn' => 'চাঁদপুর', 'district_code' => 'CHP', 'division_state' => 'Chattogram'],
        ['district_name' => 'Rangamati', 'district_name_bn' => 'রাঙ্গামাটি', 'district_code' => 'RNG', 'division_state' => 'Chattogram'],
        ['district_name' => 'Khagrachhari', 'district_name_bn' => 'খাগড়াছড়ি', 'district_code' => 'KGC', 'division_state' => 'Chattogram'],
        ['district_name' => 'Bandarban', 'district_name_bn' => 'বান্দরবান', 'district_code' => 'BDB', 'division_state' => 'Chattogram'],

        // Rajshahi Division
        ['district_name' => 'Rajshahi', 'district_name_bn' => 'রাজশাহী', 'district_code' => 'RJH', 'division_state' => 'Rajshahi'],
        ['district_name' => 'Bogura', 'district_name_bn' => 'বগুড়া', 'district_code' => 'BGR', 'division_state' => 'Rajshahi'],
        ['district_name' => 'Pabna', 'district_name_bn' => 'পাবনা', 'district_code' => 'PBN', 'division_state' => 'Rajshahi'],
        ['district_name' => 'Sirajganj', 'district_name_bn' => 'সিরাজগঞ্জ', 'district_code' => 'SRG', 'division_state' => 'Rajshahi'],
        ['district_name' => 'Naogaon', 'district_name_bn' => 'নওগাঁ', 'district_code' => 'NGN', 'division_state' => 'Rajshahi'],
        ['district_name' => 'Natore', 'district_name_bn' => 'নাটোর', 'district_code' => 'NTR', 'division_state' => 'Rajshahi'],
        ['district_name' => 'Joypurhat', 'district_name_bn' => 'জয়পুরহাট', 'district_code' => 'JPH', 'division_state' => 'Rajshahi'],
        ['district_name' => 'Chapainawabganj', 'district_name_bn' => 'চাঁপাইনবাবগঞ্জ', 'district_code' => 'CNG', 'division_state' => 'Rajshahi'],

        // Khulna Division
        ['district_name' => 'Khulna', 'district_name_bn' => 'খুলনা', 'district_code' => 'KLN', 'division_state' => 'Khulna'],
        ['district_name' => 'Jashore', 'district_name_bn' => 'যশোর', 'district_code' => 'JSR', 'division_state' => 'Khulna'],
        ['district_name' => 'Kushtia', 'district_name_bn' => 'কুষ্টিয়া', 'district_code' => 'KST', 'division_state' => 'Khulna'],
        ['district_name' => 'Satkhira', 'district_name_bn' => 'সাতক্ষীরা', 'district_code' => 'STK', 'division_state' => 'Khulna'],
        ['district_name' => 'Bagerhat', 'district_name_bn' => 'বাগেরহাট', 'district_code' => 'BGT', 'division_state' => 'Khulna'],
        ['district_name' => 'Jhenaidah', 'district_name_bn' => 'ঝিনাইদহ', 'district_code' => 'JND', 'division_state' => 'Khulna'],
        ['district_name' => 'Chuadanga', 'district_name_bn' => 'চুয়াডাঙ্গা', 'district_code' => 'CDG', 'division_state' => 'Khulna'],
        ['district_name' => 'Meherpur', 'district_name_bn' => 'মেহেরপুর', 'district_code' => 'MHP', 'division_state' => 'Khulna'],
        ['district_name' => 'Magura', 'district_name_bn' => 'মাগুরা', 'district_code' => 'MGR', 'division_state' => 'Khulna'],
        ['district_name' => 'Narail', 'district_name_bn' => 'নড়াইল', 'district_code' => 'NRL', 'division_state' => 'Khulna'],

        // Barishal Division
        ['district_name' => 'Barishal', 'district_name_bn' => 'বরিশাল', 'district_code' => 'BSL', 'division_state' => 'Barishal'],
        ['district_name' => 'Bhola', 'district_name_bn' => 'ভোলা', 'district_code' => 'BHL', 'division_state' => 'Barishal'],
        ['district_name' => 'Patuakhali', 'district_name_bn' => 'পটুয়াখালী', 'district_code' => 'PTK', 'division_state' => 'Barishal'],
        ['district_name' => 'Pirojpur', 'district_name_bn' => 'পিরোজপুর', 'district_code' => 'PRJ', 'division_state' => 'Barishal'],
        ['district_name' => 'Barguna', 'district_name_bn' => 'বরগুনা', 'district_code' => 'BRG', 'division_state' => 'Barishal'],
        ['district_name' => 'Jhalokathi', 'district_name_bn' => 'ঝালকাঠি', 'district_code' => 'JLK', 'division_state' => 'Barishal'],

        // Sylhet Division
        ['district_name' => 'Sylhet', 'district_name_bn' => 'সিলেট', 'district_code' => 'SYL', 'division_state' => 'Sylhet'],
        ['district_name' => 'Moulvibazar', 'district_name_bn' => 'মৌলভীবাজার', 'district_code' => 'MLB', 'division_state' => 'Sylhet'],
        ['district_name' => 'Habiganj', 'district_name_bn' => 'হবিগঞ্জ', 'district_code' => 'HBG', 'division_state' => 'Sylhet'],
        ['district_name' => 'Sunamganj', 'district_name_bn' => 'সুনামগঞ্জ', 'district_code' => 'SNG', 'division_state' => 'Sylhet'],

        // Rangpur Division
        ['district_name' => 'Rangpur', 'district_name_bn' => 'রংপুর', 'district_code' => 'RPR', 'division_state' => 'Rangpur'],
        ['district_name' => 'Dinajpur', 'district_name_bn' => 'দিনাজপুর', 'district_code' => 'DNP', 'division_state' => 'Rangpur'],
        ['district_name' => 'Gaibandha', 'district_name_bn' => 'গাইবান্ধা', 'district_code' => 'GBD', 'division_state' => 'Rangpur'],
        ['district_name' => 'Kurigram', 'district_name_bn' => 'কুড়িগ্রাম', 'district_code' => 'KRG', 'division_state' => 'Rangpur'],
        ['district_name' => 'Lalmonirhat', 'district_name_bn' => 'লালমনিরহাট', 'district_code' => 'LMH', 'division_state' => 'Rangpur'],
        ['district_name' => 'Nilphamari', 'district_name_bn' => 'নীলফামারী', 'district_code' => 'NPM', 'division_state' => 'Rangpur'],
        ['district_name' => 'Panchagarh', 'district_name_bn' => 'পঞ্চগড়', 'district_code' => 'PNC', 'division_state' => 'Rangpur'],
        ['district_name' => 'Thakurgaon', 'district_name_bn' => 'ঠাকুরগাঁও', 'district_code' => 'TKG', 'division_state' => 'Rangpur'],

        // Mymensingh Division
        ['district_name' => 'Mymensingh', 'district_name_bn' => 'ময়মনসিংহ', 'district_code' => 'MYM', 'division_state' => 'Mymensingh'],
        ['district_name' => 'Jamalpur', 'district_name_bn' => 'জামালপুর', 'district_code' => 'JML', 'division_state' => 'Mymensingh'],
        ['district_name' => 'Netrokona', 'district_name_bn' => 'নেত্রকোণা', 'district_code' => 'NTK', 'division_state' => 'Mymensingh'],
        ['district_name' => 'Sherpur', 'district_name_bn' => 'শেরপুর', 'district_code' => 'SHP', 'division_state' => 'Mymensingh']
    ];

    $checkStmt = $pdo->prepare("SELECT district_id FROM districts WHERE district_name = ? LIMIT 1");
    $insertStmt = $pdo->prepare("INSERT INTO districts (district_name, district_name_bn, district_code, division_state, country_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $updateStmt = $pdo->prepare("UPDATE districts SET district_name_bn = ?, district_code = ?, division_state = ?, country_id = ?, status = 'active' WHERE district_id = ?");

    $districtMap = [];

    foreach ($districts as $d) {
        $checkStmt->execute([$d['district_name']]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $districtId = (int)$existing['district_id'];
            $updateStmt->execute([$d['district_name_bn'], $d['district_code'], $d['division_state'], $countryId, $districtId]);
        } else {
            $insertStmt->execute([$d['district_name'], $d['district_name_bn'], $d['district_code'], $d['division_state'], $countryId]);
            $districtId = (int)$pdo->lastInsertId();
        }
        $districtMap[$d['district_name']] = $districtId;
    }

    echo "Districts updated with Bangla names successfully!\n";

    // 3. Seed Sub-Districts / Upazilas with Bangla Names
    $subDistricts = [
        // Dhaka
        ['district' => 'Dhaka', 'sub_district_name' => 'Dhamrai', 'sub_district_name_bn' => 'ধামরাই', 'code' => 'DHM', 'postal' => '1350'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Dohar', 'sub_district_name_bn' => 'দোহার', 'code' => 'DHR', 'postal' => '1330'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Keraniganj', 'sub_district_name_bn' => 'কেরানীগঞ্জ', 'code' => 'KRG', 'postal' => '1310'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Nawabganj', 'sub_district_name_bn' => 'নবাবগঞ্জ', 'code' => 'NWG', 'postal' => '1320'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Savar', 'sub_district_name_bn' => 'সাভার', 'code' => 'SVR', 'postal' => '1340'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Gulshan', 'sub_district_name_bn' => 'গুলশান', 'code' => 'GLS', 'postal' => '1212'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Mirpur', 'sub_district_name_bn' => 'মিরপুর', 'code' => 'MRP', 'postal' => '1216'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Uttara', 'sub_district_name_bn' => 'উত্তরা', 'code' => 'UTR', 'postal' => '1230'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Tejgaon', 'sub_district_name_bn' => 'তেজগাঁও', 'code' => 'TJG', 'postal' => '1215'],
        ['district' => 'Dhaka', 'sub_district_name' => 'Dhanmondi', 'sub_district_name_bn' => 'ধানমন্ডি', 'code' => 'DHM', 'postal' => '1205'],

        // Gazipur
        ['district' => 'Gazipur', 'sub_district_name' => 'Gazipur Sadar', 'sub_district_name_bn' => 'গাজীপুর সদর', 'code' => 'GZS', 'postal' => '1700'],
        ['district' => 'Gazipur', 'sub_district_name' => 'Kaliakair', 'sub_district_name_bn' => 'কালিয়াকৈর', 'code' => 'KKR', 'postal' => '1750'],
        ['district' => 'Gazipur', 'sub_district_name' => 'Kaliganj', 'sub_district_name_bn' => 'কালিগঞ্জ', 'code' => 'KGJ', 'postal' => '1720'],
        ['district' => 'Gazipur', 'sub_district_name' => 'Kapasia', 'sub_district_name_bn' => 'কাপাসিয়া', 'code' => 'KPS', 'postal' => '1730'],
        ['district' => 'Gazipur', 'sub_district_name' => 'Sreepur', 'sub_district_name_bn' => 'শ্রীপুর', 'code' => 'SRP', 'postal' => '1740'],

        // Narayanganj
        ['district' => 'Narayanganj', 'sub_district_name' => 'Narayanganj Sadar', 'sub_district_name_bn' => 'নারায়নগঞ্জ সদর', 'code' => 'NJS', 'postal' => '1400'],
        ['district' => 'Narayanganj', 'sub_district_name' => 'Araihazar', 'sub_district_name_bn' => 'আড়াইহাজার', 'code' => 'AHZ', 'postal' => '1450'],
        ['district' => 'Narayanganj', 'sub_district_name' => 'Bandar', 'sub_district_name_bn' => 'বন্দর', 'code' => 'BDR', 'postal' => '1410'],
        ['district' => 'Narayanganj', 'sub_district_name' => 'Rupganj', 'sub_district_name_bn' => 'রূপগঞ্জ', 'code' => 'RPG', 'postal' => '1460'],
        ['district' => 'Narayanganj', 'sub_district_name' => 'Sonargaon', 'sub_district_name_bn' => 'সোনারগাঁ', 'code' => 'SNG', 'postal' => '1440'],

        // Chattogram
        ['district' => 'Chattogram', 'sub_district_name' => 'Anwara', 'sub_district_name_bn' => 'আনোয়ারা', 'code' => 'ANW', 'postal' => '4370'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Banshkhali', 'sub_district_name_bn' => 'বাঁশখালী', 'code' => 'BSK', 'postal' => '4390'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Boalkhali', 'sub_district_name_bn' => 'বোয়ালখালী', 'code' => 'BLK', 'postal' => '4360'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Chandanaish', 'sub_district_name_bn' => 'চন্দনাইশ', 'code' => 'CDN', 'postal' => '4380'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Fatikchhari', 'sub_district_name_bn' => 'ফটিকছড়ি', 'code' => 'FTK', 'postal' => '4350'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Hathazari', 'sub_district_name_bn' => 'হাঠহাজারী', 'code' => 'HTZ', 'postal' => '4330'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Mirsharai', 'sub_district_name_bn' => 'মীরসরাই', 'code' => 'MRS', 'postal' => '4320'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Patiya', 'sub_district_name_bn' => 'পটিয়া', 'code' => 'PTY', 'postal' => '4370'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Rangunia', 'sub_district_name_bn' => 'রাঙ্গুনিয়া', 'code' => 'RNG', 'postal' => '4361'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Raozan', 'sub_district_name_bn' => 'রাউজান', 'code' => 'RZN', 'postal' => '4340'],
        ['district' => 'Chattogram', 'sub_district_name' => 'Sitakunda', 'sub_district_name_bn' => 'সীতাকুণ্ড', 'code' => 'STK', 'postal' => '4310']
    ];

    $checkSub = $pdo->prepare("SELECT sub_district_id FROM sub_districts WHERE sub_district_name = ? AND district_id = ? LIMIT 1");
    $insertSub = $pdo->prepare("INSERT INTO sub_districts (sub_district_name, sub_district_name_bn, sub_district_code, postal_code, district_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $updateSub = $pdo->prepare("UPDATE sub_districts SET sub_district_name_bn = ?, sub_district_code = ?, postal_code = ?, status = 'active' WHERE sub_district_id = ?");

    foreach ($subDistricts as $sd) {
        $dId = $districtMap[$sd['district']] ?? null;
        if ($dId) {
            $checkSub->execute([$sd['sub_district_name'], $dId]);
            $existing = $checkSub->fetch();

            if ($existing) {
                $subId = (int)$existing['sub_district_id'];
                $updateSub->execute([$sd['sub_district_name_bn'], $sd['code'], $sd['postal'], $subId]);
            } else {
                $insertSub->execute([$sd['sub_district_name'], $sd['sub_district_name_bn'], $sd['code'], $sd['postal'], $dId]);
            }
        }
    }

    echo "Sub-districts updated with Bangla names successfully!\n";

} catch (Exception $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
}
