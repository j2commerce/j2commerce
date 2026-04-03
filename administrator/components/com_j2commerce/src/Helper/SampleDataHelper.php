<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;


/**
 * Generates and removes sample data for a J2Commerce store.
 *
 * All sample data rows are tagged via metakey='j2commerce-sample-data' on the
 * related #__content articles so they can be cleanly reversed with remove().
 */
final class SampleDataHelper
{
    private const SAMPLE_TAG = 'j2commerce-sample-data';

    private const PROFILES = [
        'minimal' => [
            'categories'    => 3,
            'simple'        => 5,
            'variable'      => 2,
            'configurable'  => 0,
            'bundle'        => 0,
            'downloadable'  => 0,
            'customers'     => 5,
            'orders'        => 10,
            'options'       => 2,
            'manufacturers' => 2,
            'coupons'       => 2,
            'vouchers'      => 2,
            'date_range_days' => 30,
        ],
        'standard' => [
            'categories'    => 5,
            'simple'        => 10,
            'variable'      => 5,
            'configurable'  => 3,
            'bundle'        => 2,
            'downloadable'  => 2,
            'customers'     => 20,
            'orders'        => 50,
            'options'       => 5,
            'manufacturers' => 5,
            'coupons'       => 5,
            'vouchers'      => 3,
            'date_range_days' => 90,
        ],
        'full' => [
            'categories'    => 10,
            'simple'        => 40,
            'variable'      => 25,
            'configurable'  => 15,
            'bundle'        => 10,
            'downloadable'  => 10,
            'customers'     => 100,
            'orders'        => 500,
            'options'       => 10,
            'manufacturers' => 15,
            'coupons'       => 15,
            'vouchers'      => 5,
            'date_range_days' => 365,
        ],
    ];

    private const FIRST_NAMES = [
        'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
        'William', 'Barbara', 'David', 'Elizabeth', 'Richard', 'Susan', 'Joseph', 'Jessica',
        'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa',
        'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley',
        'Steven', 'Dorothy', 'Paul', 'Kimberly', 'Andrew', 'Emily', 'Kenneth', 'Donna',
        'Joshua', 'Michelle', 'Kevin', 'Carol', 'Brian', 'Amanda', 'George', 'Melissa',
        'Timothy', 'Deborah', 'Ronald', 'Stephanie', 'Edward', 'Rebecca', 'Jason', 'Sharon',
        'Jeffrey', 'Laura', 'Ryan', 'Cynthia', 'Jacob', 'Kathleen', 'Gary', 'Amy',
    ];

    private const LAST_NAMES = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
        'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
        'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker',
        'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
        'Carter', 'Roberts', 'Turner', 'Phillips', 'Evans', 'Parker', 'Collins', 'Edwards',
    ];

    private const PRODUCT_NAMES = [
        'electronics' => [
            'Wireless Bluetooth Headphones', 'USB-C Hub Adapter', 'Smart LED Desk Lamp',
            'Portable Power Bank 20000mAh', 'Mechanical Keyboard RGB', 'Ultrawide Monitor 34"',
            'Noise Cancelling Earbuds', 'Wireless Charging Pad', 'USB-C Docking Station',
            'Laptop Stand Adjustable', 'Smart Home Hub', 'Webcam 4K Ultra HD',
            'Portable SSD 1TB', 'Gaming Mouse Wireless', 'Bluetooth Speaker Waterproof',
            'Screen Cleaner Kit', 'Cable Management Box', 'HDMI Switch 4-Port',
            'USB Hub 7-Port', 'Monitor Arm Dual', 'Laptop Cooling Pad',
            'LED Strip Lights RGB', 'Smart Plug Wi-Fi', 'Power Strip Surge Protector',
        ],
        'clothing' => [
            'Classic Fit Oxford Shirt', 'Slim Cargo Pants', 'Athletic Performance Hoodie',
            'Running Shorts Lightweight', 'Waterproof Hiking Jacket', 'Merino Wool Socks',
            'Stretch Denim Jeans', 'Polo Shirt Breathable', 'Fleece Quarter-Zip Pullover',
            'Compression Base Layer', 'Casual Chino Trousers', 'V-Neck T-Shirt Cotton',
            'Windbreaker Jacket', 'Yoga Leggings High-Waist', 'Sports Bra Supportive',
            'Swim Trunks Quick-Dry', 'Winter Beanie Thermal', 'Fingerless Gloves',
            'Baseball Cap Adjustable', 'Crew Neck Sweater', 'Linen Shirt Long Sleeve',
            'Track Pants Tapered', 'Puffer Vest Lightweight', 'Rain Pants Waterproof',
        ],
        'home' => [
            'Bamboo Cutting Board Set', 'Stainless Steel Water Bottle', 'Cast Iron Skillet 12"',
            'Non-Stick Frying Pan', 'Salad Spinner Large', 'French Press Coffee Maker',
            'Ceramic Plant Pot Set', 'Throw Pillow Covers', 'Blackout Curtains Pair',
            'Bamboo Organiser Drawer', 'Scented Candle Set', 'Essential Oil Diffuser',
            'Bathroom Shelf Bamboo', 'Shower Caddy Tension', 'Soap Dispenser Set',
            'Kitchen Towel Set', 'Reusable Bag Set Grocery', 'Food Storage Containers',
            'Spice Rack Rotating', 'Knife Block Set 7-Piece', 'Compost Bin Kitchen',
            'Herb Garden Indoor Kit', 'Door Mat Entrance', 'Picture Frame Set',
        ],
        'sporting' => [
            'Yoga Mat Non-Slip', 'Resistance Bands Set', 'Jump Rope Speed Cable',
            'Foam Roller Deep Tissue', 'Adjustable Dumbbell Set', 'Pull-Up Bar Doorway',
            'Running Belt Waterproof', 'Cycling Gloves Padded', 'Water Bottle Sport 32oz',
            'Tennis Racket Beginner', 'Badminton Set Complete', 'Volleyball Indoor/Outdoor',
            'Fitness Tracker Band', 'Gym Bag Large Duffel', 'Weight Lifting Belt',
            'Swimming Goggles Anti-Fog', 'Camping Headlamp Rechargeable', 'Trekking Poles Pair',
            'Soccer Ball Size 5', 'Basketball Official Size', 'Frisbee Disc Ultimate',
            'Skateboard Complete', 'Bicycle Helmet Adult', 'Knee Pads Protective',
        ],
        'books' => [
            'The Art of Clean Code', 'Business Strategy Essentials', 'Mindful Living Guide',
            'Photography for Beginners', 'Cooking with Whole Foods', 'Financial Freedom Now',
            'JavaScript: The Good Parts', 'Learning Python 4th Edition', 'UX Design Handbook',
            'Marketing Psychology', 'Fitness After 40', 'Travel Photography Secrets',
            'Home Garden Encyclopedia', 'DIY Electronics Projects', 'The Entrepreneur Mindset',
            'Watercolour Techniques', 'Chess Strategy Mastery', 'Meditation Foundations',
            'Plant-Based Nutrition Guide', 'Digital Marketing Complete', 'Creative Writing Workshop',
            'Small Business Accounting', 'Yoga for Athletes', 'History of Computing',
        ],
    ];

    private const CATEGORY_GROUPS = [
        ['name' => 'Electronics', 'alias' => 'electronics', 'key' => 'electronics', 'children' => [
            ['name' => 'Laptops & Computers', 'alias' => 'laptops-computers'],
            ['name' => 'Smartphones & Tablets', 'alias' => 'smartphones-tablets'],
            ['name' => 'Accessories', 'alias' => 'accessories'],
        ]],
        ['name' => 'Clothing', 'alias' => 'clothing', 'key' => 'clothing', 'children' => [
            ['name' => "Men's Clothing", 'alias' => 'mens-clothing'],
            ['name' => "Women's Clothing", 'alias' => 'womens-clothing'],
        ]],
        ['name' => 'Home & Garden', 'alias' => 'home-garden', 'key' => 'home', 'children' => []],
        ['name' => 'Sporting Goods', 'alias' => 'sporting-goods', 'key' => 'sporting', 'children' => []],
        ['name' => 'Books & Media', 'alias' => 'books-media', 'key' => 'books', 'children' => []],
        ['name' => 'Accessories', 'alias' => 'accessories-misc', 'key' => 'electronics', 'children' => []],
        ['name' => 'Garden Tools', 'alias' => 'garden-tools', 'key' => 'home', 'children' => []],
        ['name' => 'Fitness Equipment', 'alias' => 'fitness-equipment', 'key' => 'sporting', 'children' => []],
        ['name' => 'Kitchen & Dining', 'alias' => 'kitchen-dining', 'key' => 'home', 'children' => []],
        ['name' => 'Outdoor & Camping', 'alias' => 'outdoor-camping', 'key' => 'sporting', 'children' => []],
    ];

    private const MANUFACTURER_NAMES = [
        'TechNova', 'StyleCraft', 'EcoHome', 'ActivePro', 'ReadWell Publishing',
        'GadgetPrime', 'UrbanWear', 'GreenNest', 'SportMax', 'MediaHouse',
        'InnoTech', 'FashionForward', 'HomeEssentials', 'FitLife', 'BookWorld',
    ];

    private const ADDRESS_DATA = [
        ['city' => 'New York', 'zone_name' => 'New York', 'zone_id' => 488, 'country_id' => 223, 'country_name' => 'United States', 'zip' => '10001'],
        ['city' => 'Los Angeles', 'zone_name' => 'California', 'zone_id' => 468, 'country_id' => 223, 'country_name' => 'United States', 'zip' => '90001'],
        ['city' => 'Chicago', 'zone_name' => 'Illinois', 'zone_id' => 474, 'country_id' => 223, 'country_name' => 'United States', 'zip' => '60601'],
        ['city' => 'Houston', 'zone_name' => 'Texas', 'zone_id' => 500, 'country_id' => 223, 'country_name' => 'United States', 'zip' => '77001'],
        ['city' => 'Phoenix', 'zone_name' => 'Arizona', 'zone_id' => 466, 'country_id' => 223, 'country_name' => 'United States', 'zip' => '85001'],
        ['city' => 'Toronto', 'zone_name' => 'Ontario', 'zone_id' => 851, 'country_id' => 38, 'country_name' => 'Canada', 'zip' => 'M5A 1A1'],
        ['city' => 'Vancouver', 'zone_name' => 'British Columbia', 'zone_id' => 843, 'country_id' => 38, 'country_name' => 'Canada', 'zip' => 'V5K 0A1'],
        ['city' => 'London', 'zone_name' => 'England', 'zone_id' => 0, 'country_id' => 222, 'country_name' => 'United Kingdom', 'zip' => 'SW1A 1AA'],
        ['city' => 'Manchester', 'zone_name' => 'England', 'zone_id' => 0, 'country_id' => 222, 'country_name' => 'United Kingdom', 'zip' => 'M1 1AE'],
        ['city' => 'Berlin', 'zone_name' => 'Berlin', 'zone_id' => 0, 'country_id' => 81, 'country_name' => 'Germany', 'zip' => '10115'],
        ['city' => 'Sydney', 'zone_name' => 'New South Wales', 'zone_id' => 0, 'country_id' => 13, 'country_name' => 'Australia', 'zip' => '2000'],
    ];

    private const STATUS_DISTRIBUTION = [
        1 => 40,  // Confirmed
        4 => 15,  // Pending
        3 => 15,  // Processing
        5 => 20,  // Shipped/Complete
        6 => 5,   // Cancelled
        7 => 5,   // Refunded
    ];

    private const STATUS_NAMES = [
        1 => 'Confirmed',
        3 => 'Processing',
        4 => 'Pending',
        5 => 'Complete',
        6 => 'Cancelled',
        7 => 'Refunded',
    ];

    private const COUPONS_DATA = [
        ['name' => 'Welcome Discount', 'code' => 'WELCOME10', 'value' => 10.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Summer Sale', 'code' => 'SUMMER20', 'value' => 20.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Free Shipping', 'code' => 'FREESHIP', 'value' => 0.00, 'value_type' => 'percentage', 'free_shipping' => 1, 'min_subtotal' => '50'],
        ['name' => 'Fixed Discount', 'code' => 'SAVE5', 'value' => 5.00, 'value_type' => 'fixed', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'VIP Discount', 'code' => 'VIP25', 'value' => 25.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Flash Sale', 'code' => 'FLASH15', 'value' => 15.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Bundle Saver', 'code' => 'BUNDLE30', 'value' => 30.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '100'],
        ['name' => 'Loyalty Reward', 'code' => 'LOYAL10', 'value' => 10.00, 'value_type' => 'fixed', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Clearance Deal', 'code' => 'CLEAR40', 'value' => 40.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'New Year Offer', 'code' => 'NEWYEAR', 'value' => 12.00, 'value_type' => 'percentage', 'free_shipping' => 1, 'min_subtotal' => '75'],
        ['name' => 'Back to School', 'code' => 'BTS2026', 'value' => 18.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Holiday Special', 'code' => 'HOLIDAY', 'value' => 25.00, 'value_type' => 'fixed', 'free_shipping' => 0, 'min_subtotal' => '150'],
        ['name' => 'Early Bird', 'code' => 'EARLY20', 'value' => 20.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Referral Bonus', 'code' => 'REFER15', 'value' => 15.00, 'value_type' => 'fixed', 'free_shipping' => 0, 'min_subtotal' => '0'],
        ['name' => 'Student Discount', 'code' => 'STUDENT', 'value' => 10.00, 'value_type' => 'percentage', 'free_shipping' => 0, 'min_subtotal' => '0'],
    ];

    private const VOUCHERS_DATA = [
        ['code' => 'GIFT25', 'value' => 25.00, 'type' => 'fixed', 'subject' => 'Gift Card - $25', 'email_to' => 'recipient@example.com', 'body' => 'Enjoy this $25 gift card for your next purchase!'],
        ['code' => 'GIFT50', 'value' => 50.00, 'type' => 'fixed', 'subject' => 'Gift Card - $50', 'email_to' => 'friend@example.com', 'body' => 'Here is a $50 gift card just for you!'],
        ['code' => 'GIFT100', 'value' => 100.00, 'type' => 'fixed', 'subject' => 'Gift Card - $100', 'email_to' => 'family@example.com', 'body' => 'Treat yourself with this $100 gift card!'],
        ['code' => 'BDAY20', 'value' => 20.00, 'type' => 'fixed', 'subject' => 'Birthday Voucher', 'email_to' => 'birthday@example.com', 'body' => 'Happy Birthday! Enjoy this $20 voucher on us!'],
        ['code' => 'REWARD15', 'value' => 15.00, 'type' => 'fixed', 'subject' => 'Loyalty Reward Voucher', 'email_to' => 'loyal@example.com', 'body' => 'Thank you for being a loyal customer. Here is a $15 reward!'],
    ];

    private const DESCRIPTIONS = [
        'electronics' => [
            'introtext' => '<p>Experience cutting-edge technology with the <strong>%s</strong>. Designed for performance and built to last, this product delivers everything you need for modern life.</p>',
            'fulltext'  => '<p>The <strong>%s</strong> combines innovative engineering with sleek design to give you a product that stands out from the crowd. Whether you\'re a professional or an enthusiast, this device will exceed your expectations.</p><h3>Key Features</h3><ul><li>Premium build quality with durable materials</li><li>Advanced technology for maximum performance</li><li>Intuitive design for effortless use</li><li>Energy efficient and environmentally responsible</li><li>Backed by our comprehensive warranty</li></ul><h3>Specifications</h3><p>Compatible with all major operating systems and platforms. Plug-and-play installation requires no technical expertise.</p>',
        ],
        'clothing' => [
            'introtext' => '<p>Elevate your wardrobe with the <strong>%s</strong>. Crafted from premium materials, this piece offers the perfect blend of comfort and style for everyday wear.</p>',
            'fulltext'  => '<p>The <strong>%s</strong> is designed for those who demand quality without sacrificing comfort. Made from carefully selected fabrics, it offers a fit that flatters every body type and keeps you looking sharp all day long.</p><h3>Features</h3><ul><li>Premium fabric blend for comfort and durability</li><li>Available in multiple sizes and colours</li><li>Easy care — machine washable</li><li>Reinforced stitching at stress points</li><li>Versatile style for casual and smart-casual occasions</li></ul><h3>Care Instructions</h3><p>Machine wash cold with similar colours. Tumble dry low. Do not bleach. Iron on low heat if needed.</p>',
        ],
        'home' => [
            'introtext' => '<p>Transform your living space with the <strong>%s</strong>. Thoughtfully designed to blend seamlessly with any home decor while delivering outstanding functionality.</p>',
            'fulltext'  => '<p>The <strong>%s</strong> is the perfect addition to any home. Crafted with attention to detail and made from sustainable materials, it brings both beauty and practicality to your everyday life.</p><h3>Why You\'ll Love It</h3><ul><li>Sustainable and eco-friendly materials</li><li>Timeless design that complements any decor</li><li>Easy to clean and maintain</li><li>Durable construction for years of use</li><li>Makes an ideal gift for any occasion</li></ul><h3>Dimensions</h3><p>Please refer to the size guide for exact measurements. Assembly instructions included where required.</p>',
        ],
        'sporting' => [
            'introtext' => '<p>Take your performance to the next level with the <strong>%s</strong>. Engineered for athletes and fitness enthusiasts who demand the best from their equipment.</p>',
            'fulltext'  => '<p>The <strong>%s</strong> is designed by athletes for athletes. Whether you\'re training for competition or staying fit for life, this equipment gives you the edge you need to reach your goals.</p><h3>Performance Benefits</h3><ul><li>Professional-grade materials for peak performance</li><li>Ergonomic design reduces fatigue and injury risk</li><li>Suitable for all fitness levels from beginner to advanced</li><li>Lightweight yet incredibly durable</li><li>Tested by certified sports performance coaches</li></ul><h3>Training Tips</h3><p>For best results, incorporate this equipment into a structured training programme. Consult a fitness professional for personalised guidance.</p>',
        ],
        'books' => [
            'introtext' => '<p>Expand your knowledge and skills with <strong>%s</strong>. Written by industry experts, this book delivers practical insights you can apply immediately.</p>',
            'fulltext'  => '<p><strong>%s</strong> is a comprehensive guide that takes you from the fundamentals to advanced techniques. Whether you\'re a complete beginner or looking to sharpen existing skills, this book has something valuable to offer.</p><h3>What\'s Inside</h3><ul><li>Step-by-step guidance with real-world examples</li><li>Expert tips and insider knowledge from practitioners</li><li>Practical exercises to reinforce your learning</li><li>Reference material you\'ll return to again and again</li><li>Up-to-date content reflecting current best practices</li></ul><h3>Who Is This For</h3><p>This book is suitable for readers at all stages. Whether you are just starting out or are an experienced professional seeking fresh perspectives, you will find immense value in these pages.</p>',
        ],
    ];

    private const OPTIONS_DATA = [
        ['name' => 'Color', 'type' => 'radio', 'values' => ['Red', 'Blue', 'Green', 'Black', 'White', 'Yellow', 'Purple', 'Orange']],
        ['name' => 'Size', 'type' => 'select', 'values' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL']],
        ['name' => 'Material', 'type' => 'select', 'values' => ['Cotton', 'Polyester', 'Leather', 'Wood', 'Metal', 'Plastic']],
        ['name' => 'Storage', 'type' => 'radio', 'values' => ['64GB', '128GB', '256GB', '512GB', '1TB']],
        ['name' => 'Warranty', 'type' => 'checkbox', 'values' => ['1 Year', '2 Year', '3 Year', 'Lifetime']],
        ['name' => 'Finish', 'type' => 'select', 'values' => ['Matte', 'Glossy', 'Satin', 'Brushed']],
        ['name' => 'Pack Size', 'type' => 'radio', 'values' => ['Single', 'Pack of 2', 'Pack of 5', 'Pack of 10']],
        ['name' => 'Edition', 'type' => 'select', 'values' => ['Standard', 'Professional', 'Enterprise']],
        ['name' => 'Style', 'type' => 'select', 'values' => ['Classic', 'Modern', 'Vintage', 'Sport', 'Casual']],
        ['name' => 'Weight', 'type' => 'radio', 'values' => ['Light', 'Medium', 'Heavy']],
    ];

    public function __construct(private DatabaseInterface $db) {}

    public function isLoaded(): bool
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('metakey') . ' LIKE :tag')
            ->bind(':tag', $tag);
        $tag   = '%' . self::SAMPLE_TAG . '%';
        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    public function load(string $profile = 'standard', array $overrides = []): array
    {
        $cfg = array_merge(self::PROFILES[$profile] ?? self::PROFILES['standard'], $overrides);

        $now      = date('Y-m-d H:i:s');
        $today    = time();
        $summary  = [];

        // 1. Create categories
        $rootCatId = $this->getOrCreateJ2CommerceRootCategory($now);
        $catIds    = $this->createCategories((int) $cfg['categories'], $rootCatId, $now);
        $summary['categories'] = count($catIds);

        // 2. Create manufacturers
        $mfgIds  = $this->createManufacturers((int) $cfg['manufacturers'], $now);
        $summary['manufacturers'] = count($mfgIds);

        // 3. Create options
        $optionIds = $this->createOptions((int) $cfg['options']);
        $summary['options'] = count($optionIds);

        // 4. Create products
        $productIds = [];

        $simpleIds = $this->createSimpleProducts((int) $cfg['simple'], $catIds, $mfgIds, $now);
        $productIds = array_merge($productIds, $simpleIds);
        $summary['products_simple'] = count($simpleIds);

        $varIds = $this->createVariableProducts((int) $cfg['variable'], $catIds, $mfgIds, $optionIds, $now);
        $productIds = array_merge($productIds, $varIds);
        $summary['products_variable'] = count($varIds);

        if (!empty($cfg['configurable'])) {
            $cfgIds = $this->createSimpleProducts((int) $cfg['configurable'], $catIds, $mfgIds, $now, 'configurable');
            $productIds = array_merge($productIds, $cfgIds);
            $summary['products_configurable'] = count($cfgIds);
        }

        if (!empty($cfg['bundle'])) {
            $bundleIds = $this->createSimpleProducts((int) $cfg['bundle'], $catIds, $mfgIds, $now, 'bundle');
            $productIds = array_merge($productIds, $bundleIds);
            $summary['products_bundle'] = count($bundleIds);
        }

        if (!empty($cfg['downloadable'])) {
            $dlIds = $this->createSimpleProducts((int) $cfg['downloadable'], $catIds, $mfgIds, $now, 'downloadable');
            $productIds = array_merge($productIds, $dlIds);
            $summary['products_downloadable'] = count($dlIds);
        }

        // 4b. Create product images
        $imageCount = $this->createProductImages($productIds, $catIds);
        $summary['product_images'] = $imageCount;

        // 5. Create customers
        $customerIds = $this->createCustomers((int) $cfg['customers'], $now);
        $summary['customers'] = count($customerIds);

        // 6. Create orders
        $orderCount = $this->createOrders((int) $cfg['orders'], $customerIds, $productIds, (int) $cfg['date_range_days']);
        $summary['orders'] = $orderCount;

        // 7. Create coupons
        $couponCount = $this->createCoupons((int) $cfg['coupons'], $now);
        $summary['coupons'] = $couponCount;

        // 7b. Create vouchers
        $voucherCount = $this->createVouchers((int) ($cfg['vouchers'] ?? 0), $now);
        $summary['vouchers'] = $voucherCount;

        // 8. Create advanced pricing for full profile
        if ($profile === 'full' && !empty($productIds)) {
            $advancedPricingCount = $this->createAdvancedPricing($productIds, $this->db);
            $summary['advanced_pricing'] = $advancedPricingCount;
        }

        // 9. Ensure store has basic tax, shipping, and payment configured
        $storeSetup = $this->ensureStoreSetup($this->db);
        foreach ($storeSetup as $key => $value) {
            $summary['setup_' . $key] = $value;
        }

        $summary['profile'] = $profile;
        $summary['success'] = true;

        return $summary;
    }

    public function remove(): array
    {
        $db  = $this->db;
        $tag = '%' . self::SAMPLE_TAG . '%';

        // Find all sample content articles
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('metakey') . ' LIKE :tag')
            ->bind(':tag', $tag);
        $db->setQuery($query);
        $articleIds = array_column($db->loadObjectList(), 'id');

        $counts = [];

        if (!empty($articleIds)) {
            $productQuery = $db->getQuery(true)
                ->select('j2commerce_product_id')
                ->from($db->quoteName('#__j2commerce_products'))
                ->whereIn($db->quoteName('product_source_id'), $articleIds);
            $db->setQuery($productQuery);
            $productIds = array_column($db->loadObjectList(), 'j2commerce_product_id');

            if (!empty($productIds)) {
                // Remove product images first
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_productimages'))
                        ->whereIn($db->quoteName('product_id'), $productIds)
                );
                $db->execute();
                $counts['product_images'] = $db->getAffectedRows();

                // Collect variant IDs first for quantity cleanup
                $variantQuery = $db->getQuery(true)
                    ->select('j2commerce_variant_id')
                    ->from($db->quoteName('#__j2commerce_variants'))
                    ->whereIn($db->quoteName('product_id'), $productIds);
                $db->setQuery($variantQuery);
                $variantIds = array_column($db->loadObjectList(), 'j2commerce_variant_id');

                if (!empty($variantIds)) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_productquantities'))
                            ->whereIn($db->quoteName('variant_id'), $variantIds)
                    );
                    $db->execute();

                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                            ->whereIn($db->quoteName('variant_id'), $variantIds)
                    );
                    $db->execute();

                    // Remove advanced pricing rows for sample product variants
                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_product_prices'))
                            ->whereIn($db->quoteName('variant_id'), $variantIds)
                    );
                    $db->execute();
                    $counts['advanced_pricing'] = $db->getAffectedRows();
                }

                // Remove product option linkage
                $poQuery = $db->getQuery(true)
                    ->select('j2commerce_productoption_id')
                    ->from($db->quoteName('#__j2commerce_product_options'))
                    ->whereIn($db->quoteName('product_id'), $productIds);
                $db->setQuery($poQuery);
                $productOptionIds = array_column($db->loadObjectList(), 'j2commerce_productoption_id');

                if (!empty($productOptionIds)) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                            ->whereIn($db->quoteName('productoption_id'), $productOptionIds)
                    );
                    $db->execute();

                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_product_options'))
                            ->whereIn($db->quoteName('product_id'), $productIds)
                    );
                    $db->execute();
                }

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_variants'))
                        ->whereIn($db->quoteName('product_id'), $productIds)
                );
                $db->execute();
                $counts['variants'] = $db->getAffectedRows();

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_products'))
                        ->whereIn($db->quoteName('j2commerce_product_id'), $productIds)
                );
                $db->execute();
                $counts['products'] = $db->getAffectedRows();
            }

            $emailPattern = '%.sample@j2commerce.example';
            $userQuery = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('email') . ' LIKE :emailPattern')
                ->bind(':emailPattern', $emailPattern);
            $db->setQuery($userQuery);
            $sampleUserIds = array_column($db->loadObjectList(), 'id');

            if (!empty($sampleUserIds)) {
                $orderQuery = $db->getQuery(true)
                    ->select('order_id')
                    ->from($db->quoteName('#__j2commerce_orders'))
                    ->whereIn($db->quoteName('user_id'), $sampleUserIds);
                $db->setQuery($orderQuery);
                $orderNos = array_column($db->loadObjectList(), 'order_id');

                if (!empty($orderNos)) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_orderitems'))
                            ->whereIn($db->quoteName('order_id'), $orderNos, ParameterType::STRING)
                    );
                    $db->execute();

                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_orderinfos'))
                            ->whereIn($db->quoteName('order_id'), $orderNos, ParameterType::STRING)
                    );
                    $db->execute();

                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_orderhistories'))
                            ->whereIn($db->quoteName('order_id'), $orderNos, ParameterType::STRING)
                    );
                    $db->execute();

                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_orders'))
                            ->whereIn($db->quoteName('user_id'), $sampleUserIds)
                    );
                    $db->execute();
                    $counts['orders'] = $db->getAffectedRows();
                }

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_addresses'))
                        ->whereIn($db->quoteName('user_id'), $sampleUserIds)
                );
                $db->execute();
                $counts['addresses'] = $db->getAffectedRows();

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__user_usergroup_map'))
                        ->whereIn($db->quoteName('user_id'), $sampleUserIds)
                );
                $db->execute();

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__users'))
                        ->whereIn($db->quoteName('id'), $sampleUserIds)
                );
                $db->execute();
                $counts['customers'] = $db->getAffectedRows();
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__content'))
                    ->whereIn($db->quoteName('id'), $articleIds)
            );
            $db->execute();
            $counts['articles'] = $db->getAffectedRows();

            // Remove sample categories (tagged via metakey) using Category table for proper nested set cleanup
            $catQuery = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('metakey') . ' LIKE :tag')
                ->bind(':tag', $tag);
            $db->setQuery($catQuery);
            $sampleCatIds = array_column($db->loadObjectList(), 'id');

            if (!empty($sampleCatIds)) {
                $deleted = 0;
                foreach ($sampleCatIds as $catId) {
                    $table = Table::getInstance('Category');
                    if ($table->load((int) $catId)) {
                        $table->delete((int) $catId);
                        $deleted++;
                    }
                }
                $counts['categories'] = $deleted;

                // Rebuild nested set tree after deleting categories
                $rebuildTable = Table::getInstance('Category');
                $rebuildTable->rebuild();
            }
        }

        // Remove sample coupons tagged via coupon_name prefix
        $couponPrefix = '[SAMPLE]%';
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_coupons'))
                ->where($db->quoteName('coupon_name') . ' LIKE :couponPrefix')
                ->bind(':couponPrefix', $couponPrefix)
        );
        $db->execute();
        $counts['coupons'] = $db->getAffectedRows();

        // Remove sample vouchers tagged via subject prefix
        $voucherPrefix = '[SAMPLE]%';
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_vouchers'))
                ->where($db->quoteName('subject') . ' LIKE :voucherPrefix')
                ->bind(':voucherPrefix', $voucherPrefix)
        );
        $db->execute();
        $counts['vouchers'] = $db->getAffectedRows();

        // Remove sample options tagged via option_unique_name prefix
        $sampleOptQuery = $db->getQuery(true)
            ->select('j2commerce_option_id')
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('option_unique_name') . ' LIKE :optPrefix')
            ->bind(':optPrefix', $optPrefix);
        $optPrefix = 'sample\_%';
        $db->setQuery($sampleOptQuery);
        $sampleOptionIds = array_column($db->loadObjectList(), 'j2commerce_option_id');

        if (!empty($sampleOptionIds)) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_optionvalues'))
                    ->whereIn($db->quoteName('option_id'), $sampleOptionIds)
            );
            $db->execute();

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_options'))
                    ->whereIn($db->quoteName('j2commerce_option_id'), $sampleOptionIds)
            );
            $db->execute();
            $counts['options'] = $db->getAffectedRows();
        }

        // Remove sample manufacturer addresses (company LIKE '[SAMPLE]%')
        $sampleCompany = '[SAMPLE]%';
        $addrQuery = $db->getQuery(true)
            ->select('j2commerce_address_id')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('company') . ' LIKE :company')
            ->bind(':company', $sampleCompany);
        $db->setQuery($addrQuery);
        $sampleAddrIds = array_column($db->loadObjectList(), 'j2commerce_address_id');

        if (!empty($sampleAddrIds)) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_manufacturers'))
                    ->whereIn($db->quoteName('address_id'), $sampleAddrIds)
            );
            $db->execute();
            $counts['manufacturers'] = $db->getAffectedRows();

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_addresses'))
                    ->whereIn($db->quoteName('j2commerce_address_id'), $sampleAddrIds)
            );
            $db->execute();
        }

        $counts['success'] = true;

        return $counts;
    }

    // =========================================================================
    // Private generators
    // =========================================================================

    private function getOrCreateJ2CommerceRootCategory(string $now): int
    {
        $db = $this->db;

        // Check if a "Shop" category tagged as sample data already exists
        $ext = 'com_content';
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :ext')
            ->where($db->quoteName('alias') . ' = :alias')
            ->where($db->quoteName('metakey') . ' = :tag')
            ->bind(':ext', $ext)
            ->bind(':alias', $shopAlias)
            ->bind(':tag', $sampleTag);
        $shopAlias = 'shop';
        $sampleTag = self::SAMPLE_TAG;
        $db->setQuery($query);
        $existing = (int) $db->loadResult();

        if ($existing > 0) {
            return $existing;
        }

        // Create "Shop" as a sibling of "Uncategorised" under the global root (id=1)
        $table = Table::getInstance('Category');
        $table->extension        = 'com_content';
        $table->title            = 'Shop';
        $table->alias            = $this->uniqueAlias('shop', 'categories');
        $table->published        = 1;
        $table->access           = 1;
        $table->language         = '*';
        $table->description      = '';
        $table->note             = '';
        $table->metadesc         = '';
        $table->metakey          = self::SAMPLE_TAG;
        $table->metadata         = '';
        $table->params           = '{}';
        $table->created_time     = $now;
        $table->modified_time    = $now;
        $table->created_user_id  = 0;
        $table->modified_user_id = 0;
        $table->hits             = 0;
        $table->version          = 1;
        $table->setLocation(1, 'last-child');

        if ($table->check() && $table->store()) {
            return (int) $table->id;
        }

        return 1;
    }

    private function createCategories(int $count, int $parentId, string $now): array
    {
        $catIds = [];
        $groups = array_slice(self::CATEGORY_GROUPS, 0, $count);

        foreach ($groups as $group) {
            $alias = $this->uniqueAlias($group['alias'], 'categories');

            $table = Table::getInstance('Category');
            $table->extension   = 'com_content';
            $table->title       = $group['name'];
            $table->alias       = $alias;
            $table->published   = 1;
            $table->access      = 1;
            $table->language    = '*';
            $table->description = '';
            $table->note        = '';
            $table->metadesc    = '';
            $table->metakey     = self::SAMPLE_TAG;
            $table->metadata    = '';
            $table->params      = '{}';
            $table->created_time     = $now;
            $table->modified_time    = $now;
            $table->created_user_id  = 0;
            $table->modified_user_id = 0;
            $table->hits        = 0;
            $table->version     = 1;
            $table->setLocation($parentId, 'last-child');

            if (!$table->check() || !$table->store()) {
                continue;
            }

            $catId = (int) $table->id;

            if ($catId > 0) {
                $catIds[] = ['id' => $catId, 'key' => $group['key'] ?? 'electronics', 'name' => $group['name']];
            }
        }

        return $catIds;
    }

    private function createManufacturers(int $count, string $now): array
    {
        $db     = $this->db;
        $mfgIds = [];
        $names  = array_slice(self::MANUFACTURER_NAMES, 0, $count);

        foreach ($names as $i => $mfgName) {
            // Create an address record for the manufacturer (required FK)
            $addr          = new \stdClass();
            $addr->user_id = 0;
            $addr->first_name  = $mfgName;
            $addr->last_name   = 'HQ';
            $addr->email       = strtolower(str_replace(' ', '', $mfgName)) . '@example.com';
            $addr->address_1   = (($i + 1) * 100) . ' Commerce Ave';
            $addr->address_2   = '';
            $addr->city        = 'Sample City';
            $addr->zip         = '00000';
            $addr->zone_id     = '0';
            $addr->country_id  = '223';
            $addr->phone_1     = '+1-555-000-0000';
            $addr->phone_2     = '';
            $addr->type        = 'manufacturer';
            $addr->company     = '[SAMPLE] ' . $mfgName;
            $addr->tax_number  = '';

            $db->insertObject('#__j2commerce_addresses', $addr);
            $addrId = (int) $db->insertid();

            if ($addrId <= 0) {
                continue;
            }

            $mfg             = new \stdClass();
            $mfg->address_id = $addrId;
            $mfg->enabled    = 1;
            $mfg->ordering   = $i + 1;
            $mfg->brand_desc_id = 0;

            $db->insertObject('#__j2commerce_manufacturers', $mfg);
            $mfgId = (int) $db->insertid();

            if ($mfgId > 0) {
                $mfgIds[] = $mfgId;
            }
        }

        return $mfgIds;
    }

    private function createOptions(int $count): array
    {
        $db        = $this->db;
        $optionIds = [];
        $options   = array_slice(self::OPTIONS_DATA, 0, $count);

        foreach ($options as $i => $optData) {
            $opt                   = new \stdClass();
            $opt->type             = $optData['type'];
            $opt->option_unique_name = 'sample_' . strtolower(str_replace(' ', '_', $optData['name']));
            $opt->option_name      = $optData['name'];
            $opt->ordering         = $i + 1;
            $opt->enabled          = 1;
            $opt->option_params    = '{}';

            $db->insertObject('#__j2commerce_options', $opt);
            $optionId = (int) $db->insertid();

            if ($optionId <= 0) {
                continue;
            }

            $optionIds[] = ['id' => $optionId, 'values' => $optData['values']];

            // Create option values
            foreach ($optData['values'] as $j => $valName) {
                $val                    = new \stdClass();
                $val->option_id         = $optionId;
                $val->optionvalue_name  = $valName;
                $val->optionvalue_image = '';
                $val->ordering          = $j + 1;
                $db->insertObject('#__j2commerce_optionvalues', $val);
            }
        }

        return $optionIds;
    }

    private function createSimpleProducts(int $count, array $catIds, array $mfgIds, string $now, string $type = 'simple'): array
    {
        if ($count <= 0 || empty($catIds)) {
            return [];
        }

        $db         = $this->db;
        $productIds = [];
        $catCount   = count($catIds);
        $mfgCount   = count($mfgIds);

        $namePool = $this->buildNamePool();

        for ($i = 0; $i < $count; $i++) {
            $catEntry   = $catIds[$i % $catCount];
            $catId      = $catEntry['id'];
            $catKey     = $catEntry['key'];
            $mfgId      = $mfgCount > 0 ? $mfgIds[$i % $mfgCount] : 0;
            $price      = round(mt_rand(999, 49999) / 100, 2);
            $productName = $namePool[$catKey][$i % count($namePool[$catKey] ?: ['Product ' . ($i + 1)])];

            // Append sequence if needed for uniqueness
            $seqTag = ' #' . ($i + 1);
            $uniqueName = $productName . $seqTag;

            // Create Joomla content article as the product source
            $alias   = $this->uniqueAlias(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $uniqueName)), 'content');
            $article = new \stdClass();
            $descTemplates = self::DESCRIPTIONS[$catKey] ?? self::DESCRIPTIONS['electronics'];
            $article->title       = $uniqueName;
            $article->alias       = $alias;
            $article->introtext   = sprintf($descTemplates['introtext'], htmlspecialchars($uniqueName));
            $article->fulltext    = sprintf($descTemplates['fulltext'], htmlspecialchars($uniqueName));
            $article->state       = 1;
            $article->catid       = $catId;
            $article->created     = $now;
            $article->created_by  = 0;
            $article->created_by_alias = '';
            $article->modified    = $now;
            $article->modified_by = 0;
            $article->images      = '{}';
            $article->urls        = '{}';
            $article->attribs     = '{}';
            $article->version     = 1;
            $article->ordering    = $i;
            $article->metakey     = self::SAMPLE_TAG;
            $article->metadesc    = '';
            $article->access      = 1;
            $article->hits        = 0;
            $article->metadata    = '{}';
            $article->featured    = 0;
            $article->language    = '*';
            $article->note        = '';
            $article->asset_id    = 0;
            $article->publish_up  = null;
            $article->publish_down = null;
            $article->checked_out = null;
            $article->checked_out_time = null;

            $db->insertObject('#__content', $article);
            $articleId = (int) $db->insertid();

            if ($articleId <= 0) {
                continue;
            }

            // Create J2Commerce product record
            $prefix = match ($type) {
                'variable'     => 'VAR',
                'configurable' => 'CFG',
                'bundle'       => 'BND',
                'downloadable' => 'DLD',
                default        => 'SMPL',
            };

            $product                   = new \stdClass();
            $product->visibility       = 1;
            $product->product_source   = 'com_content';
            $product->product_source_id = $articleId;
            $product->product_type     = $type;
            $product->main_tag         = '';
            $product->taxprofile_id    = 0;
            $product->manufacturer_id  = $mfgId;
            $product->vendor_id        = 0;
            $product->has_options      = 0;
            $product->addtocart_text   = '';
            $product->enabled          = 1;
            $product->plugins          = '';
            $product->params           = '{}';
            $product->created_on       = $now;
            $product->created_by       = 0;
            $product->modified_on      = $now;
            $product->modified_by      = 0;
            $product->up_sells         = '';
            $product->cross_sells      = '';
            $product->productfilter_ids = '';
            $product->hits             = 0;

            $db->insertObject('#__j2commerce_products', $product);
            $productId = (int) $db->insertid();

            if ($productId <= 0) {
                continue;
            }

            // Create master variant
            $sku     = $prefix . '-' . strtoupper(substr($catKey, 0, 3)) . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            $variant = $this->buildVariantObject($productId, $sku, $price, $now, isMaster: true);
            $db->insertObject('#__j2commerce_variants', $variant);
            $variantId = (int) $db->insertid();

            if ($variantId > 0) {
                $qty              = new \stdClass();
                $qty->product_attributes = '[]';
                $qty->variant_id  = $variantId;
                $qty->quantity    = mt_rand(0, 200);
                $qty->on_hold     = 0;
                $qty->sold        = 0;
                $db->insertObject('#__j2commerce_productquantities', $qty);
            }

            $productIds[] = ['id' => $productId, 'variant_id' => $variantId, 'name' => $uniqueName, 'price' => $price, 'sku' => $sku];
        }

        return $productIds;
    }

    private function createVariableProducts(int $count, array $catIds, array $mfgIds, array $optionIds, string $now): array
    {
        if ($count <= 0 || empty($catIds)) {
            return [];
        }

        $db       = $this->db;
        $hasOptions = !empty($optionIds);

        // Pick the first available option for variant generation; fall back to generic labels
        $variantOption    = $hasOptions ? $optionIds[0] : null;
        $variantSuffixes  = $hasOptions ? array_slice($variantOption['values'], 0, 5) : ['Variant A', 'Variant B', 'Variant C'];

        // Load optionvalue IDs for the chosen option so we can build linkage rows
        $optionValueIdMap = [];
        if ($variantOption !== null) {
            $optQuery = $db->getQuery(true)
                ->select(['j2commerce_optionvalue_id', 'optionvalue_name'])
                ->from($db->quoteName('#__j2commerce_optionvalues'))
                ->where($db->quoteName('option_id') . ' = :optId')
                ->bind(':optId', $variantOption['id'], ParameterType::INTEGER);
            $db->setQuery($optQuery);
            foreach ($db->loadObjectList() as $row) {
                $optionValueIdMap[$row->optionvalue_name] = (int) $row->j2commerce_optionvalue_id;
            }
        }

        $productIds = [];
        $catCount   = count($catIds);
        $mfgCount   = count($mfgIds);
        $namePool   = $this->buildNamePool();
        $offset     = 1000;

        for ($i = 0; $i < $count; $i++) {
            $catEntry    = $catIds[$i % $catCount];
            $catId       = $catEntry['id'];
            $catKey      = $catEntry['key'];
            $mfgId       = $mfgCount > 0 ? $mfgIds[$i % $mfgCount] : 0;
            $basePrice   = round(mt_rand(999, 29999) / 100, 2);
            $names       = $namePool[$catKey];
            $productName = $names[($i + $offset) % count($names)] . ' (Variable) #' . ($i + 1);

            $alias   = $this->uniqueAlias(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $productName)), 'content');
            $article = new \stdClass();
            $descTemplates = self::DESCRIPTIONS[$catKey] ?? self::DESCRIPTIONS['electronics'];
            $article->title       = $productName;
            $article->alias       = $alias;
            $article->introtext   = sprintf($descTemplates['introtext'], htmlspecialchars($productName));
            $article->fulltext    = sprintf($descTemplates['fulltext'], htmlspecialchars($productName));
            $article->state       = 1;
            $article->catid       = $catId;
            $article->created     = $now;
            $article->created_by  = 0;
            $article->created_by_alias = '';
            $article->modified    = $now;
            $article->modified_by = 0;
            $article->images      = '{}';
            $article->urls        = '{}';
            $article->attribs     = '{}';
            $article->version     = 1;
            $article->ordering    = $i;
            $article->metakey     = self::SAMPLE_TAG;
            $article->metadesc    = '';
            $article->access      = 1;
            $article->hits        = 0;
            $article->metadata    = '{}';
            $article->featured    = 0;
            $article->language    = '*';
            $article->note        = '';
            $article->asset_id    = 0;
            $article->publish_up  = null;
            $article->publish_down = null;
            $article->checked_out = null;
            $article->checked_out_time = null;

            $db->insertObject('#__content', $article);
            $articleId = (int) $db->insertid();

            if ($articleId <= 0) {
                continue;
            }

            $product                    = new \stdClass();
            $product->visibility        = 1;
            $product->product_source    = 'com_content';
            $product->product_source_id = $articleId;
            $product->product_type      = 'variable';
            $product->main_tag          = '';
            $product->taxprofile_id     = 0;
            $product->manufacturer_id   = $mfgId;
            $product->vendor_id         = 0;
            $product->has_options       = 1;
            $product->addtocart_text    = '';
            $product->enabled           = 1;
            $product->plugins           = '';
            $product->params            = '{}';
            $product->created_on        = $now;
            $product->created_by        = 0;
            $product->modified_on       = $now;
            $product->modified_by       = 0;
            $product->up_sells          = '';
            $product->cross_sells       = '';
            $product->productfilter_ids = '';
            $product->hits              = 0;

            $db->insertObject('#__j2commerce_products', $product);
            $productId = (int) $db->insertid();

            if ($productId <= 0) {
                continue;
            }

            // Link product to option and create product_optionvalues rows
            $productOptionValueIds = [];
            if ($variantOption !== null) {
                $po             = new \stdClass();
                $po->option_id  = $variantOption['id'];
                $po->parent_id  = 0;
                $po->product_id = $productId;
                $po->ordering   = 1;
                $po->required   = 1;
                $po->is_variant = 1;
                $db->insertObject('#__j2commerce_product_options', $po);
                $productOptionId = (int) $db->insertid();

                if ($productOptionId > 0) {
                    foreach ($variantSuffixes as $j => $valueName) {
                        $optionValueId = $optionValueIdMap[$valueName] ?? 0;

                        $pov                                     = new \stdClass();
                        $pov->productoption_id                   = $productOptionId;
                        $pov->optionvalue_id                     = $optionValueId;
                        $pov->parent_optionvalue                 = '';
                        $pov->product_optionvalue_price          = '0.00000000';
                        $pov->product_optionvalue_prefix         = '+';
                        $pov->product_optionvalue_weight         = '0.00000000';
                        $pov->product_optionvalue_weight_prefix  = '+';
                        $pov->product_optionvalue_sku            = '';
                        $pov->product_optionvalue_default        = $j === 0 ? 1 : 0;
                        $pov->ordering                           = $j;
                        $pov->product_optionvalue_attribs        = '{}';
                        $db->insertObject('#__j2commerce_product_optionvalues', $pov);
                        $productOptionValueIds[$valueName] = (int) $db->insertid();
                    }
                }
            }

            // Create master variant
            $masterSku = 'VAR-' . strtoupper(substr($catKey, 0, 3)) . '-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
            $master    = $this->buildVariantObject($productId, $masterSku, $basePrice, $now, isMaster: true);
            $db->insertObject('#__j2commerce_variants', $master);
            $masterVariantId = (int) $db->insertid();

            if ($masterVariantId > 0) {
                $qty = new \stdClass();
                $qty->product_attributes = '[]';
                $qty->variant_id = $masterVariantId;
                $qty->quantity   = 0;
                $qty->on_hold    = 0;
                $qty->sold       = 0;
                $db->insertObject('#__j2commerce_productquantities', $qty);
            }

            // Create child variants and link each to its option value
            foreach ($variantSuffixes as $j => $suffix) {
                $childSku   = $masterSku . '-' . strtoupper(substr($suffix, 0, 2));
                $childPrice = round($basePrice + ($j * mt_rand(100, 500) / 100), 2);
                $child      = $this->buildVariantObject($productId, $childSku, $childPrice, $now, isMaster: false);
                $db->insertObject('#__j2commerce_variants', $child);
                $childVarId = (int) $db->insertid();

                if ($childVarId <= 0) {
                    continue;
                }

                $qty = new \stdClass();
                $qty->product_attributes = '[]';
                $qty->variant_id = $childVarId;
                $qty->quantity   = mt_rand(5, 100);
                $qty->on_hold    = 0;
                $qty->sold       = 0;
                $db->insertObject('#__j2commerce_productquantities', $qty);

                // Link variant to its product_optionvalue
                $povId = $productOptionValueIds[$suffix] ?? 0;

                if ($povId > 0) {
                    $pvov                          = new \stdClass();
                    $pvov->variant_id              = $childVarId;
                    $pvov->product_optionvalue_ids = (string) $povId;
                    $db->insertObject('#__j2commerce_product_variant_optionvalues', $pvov);
                }
            }

            $productIds[] = ['id' => $productId, 'variant_id' => $masterVariantId, 'name' => $productName, 'price' => $basePrice, 'sku' => $masterSku];
        }

        return $productIds;
    }

    private function createCustomers(int $count, string $now): array
    {
        $db          = $this->db;
        $customerIds = [];
        $firstNames  = self::FIRST_NAMES;
        $lastNames   = self::LAST_NAMES;
        $addrData    = self::ADDRESS_DATA;
        $addrCount   = count($addrData);

        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[$i % count($firstNames)];
            $lastName  = $lastNames[$i % count($lastNames)];
            $email     = strtolower($firstName . '.' . $lastName . $i . '.sample@j2commerce.example');
            $username  = strtolower($firstName . $lastName . $i);

            // Check for duplicate email
            $checkQuery = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('email') . ' = :email')
                ->bind(':email', $email);
            $db->setQuery($checkQuery);

            if ($db->loadResult()) {
                continue; // already exists, skip
            }

            $user               = new \stdClass();
            $user->name         = $firstName . ' ' . $lastName;
            $user->username     = $username;
            $user->email        = $email;
            $user->password     = password_hash('SamplePass123!', PASSWORD_DEFAULT);
            $user->block        = 0;
            $user->sendEmail    = 0;
            $user->registerDate = $now;
            $user->activation   = '';
            $user->params       = '{}';
            $user->otpKey       = '';
            $user->otep         = '';
            $user->requireReset = 0;
            $user->authProvider = '';
            $user->resetCount   = 0;

            $db->insertObject('#__users', $user);
            $userId = (int) $db->insertid();

            if ($userId <= 0) {
                continue;
            }

            // Add to Registered group (id=2)
            $map          = new \stdClass();
            $map->user_id = $userId;
            $map->group_id = 2;
            $db->insertObject('#__user_usergroup_map', $map);

            // Create 1-2 addresses per customer
            $addrCount2 = mt_rand(1, 2);
            for ($j = 0; $j < $addrCount2; $j++) {
                $addrTemplate = $addrData[($i + $j) % $addrCount];

                $addr             = new \stdClass();
                $addr->user_id    = $userId;
                $addr->first_name = $firstName;
                $addr->last_name  = $lastName;
                $addr->email      = $email;
                $addr->address_1  = mt_rand(1, 9999) . ' ' . $this->randomStreetName() . ' ' . $this->randomStreetType();
                $addr->address_2  = '';
                $addr->city       = $addrTemplate['city'];
                $addr->zip        = $addrTemplate['zip'];
                $addr->zone_id    = (string) $addrTemplate['zone_id'];
                $addr->country_id = (string) $addrTemplate['country_id'];
                $addr->phone_1    = $this->randomPhone();
                $addr->phone_2    = '';
                $addr->type       = $j === 0 ? 'billing' : 'shipping';
                $addr->company    = '';
                $addr->tax_number = '';

                $db->insertObject('#__j2commerce_addresses', $addr);
            }

            $customerIds[] = [
                'id'         => $userId,
                'email'      => $email,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'addr_data'  => $addrData[$i % $addrCount],
            ];
        }

        return $customerIds;
    }

    private function createOrders(int $count, array $customerIds, array $productIds, int $dateRangeDays): int
    {
        if ($count <= 0 || empty($customerIds) || empty($productIds)) {
            return 0;
        }

        $db          = $this->db;
        $created     = 0;
        $custCount   = count($customerIds);
        $prodCount   = count($productIds);
        $addrData    = self::ADDRESS_DATA;
        $addrCount   = count($addrData);

        // Build weighted status distribution
        $statusPool = [];
        foreach (self::STATUS_DISTRIBUTION as $statusId => $pct) {
            for ($j = 0; $j < $pct; $j++) {
                $statusPool[] = $statusId;
            }
        }

        $now = time();

        for ($i = 0; $i < $count; $i++) {
            $customer  = $customerIds[$i % $custCount];
            $statusId  = $statusPool[$i % count($statusPool)];
            $statusName = self::STATUS_NAMES[$statusId] ?? 'Pending';

            // Spread creation dates realistically
            $daysBack   = mt_rand(0, $dateRangeDays);
            $createdTs  = $now - ($daysBack * 86400) - mt_rand(0, 86400);
            $createdOn  = date('Y-m-d H:i:s', $createdTs);

            // Pick 1-4 items
            $itemCount  = mt_rand(1, min(4, $prodCount));
            $orderItems = [];
            $subtotal   = 0.0;

            for ($j = 0; $j < $itemCount; $j++) {
                $prod     = $productIds[($i * 7 + $j) % $prodCount];
                $qty      = mt_rand(1, 3);
                $price    = (float) ($prod['price'] ?? mt_rand(999, 9999) / 100);
                $lineTotal = round($price * $qty, 5);
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id'   => $prod['id'],
                    'variant_id'   => $prod['variant_id'] ?? 0,
                    'name'         => $prod['name'] ?? 'Sample Product',
                    'sku'          => $prod['sku'] ?? 'SMPL-000',
                    'price'        => $price,
                    'qty'          => $qty,
                    'line_total'   => $lineTotal,
                ];
            }

            $tax      = round($subtotal * 0.0825, 5);
            $shipping = $subtotal > 50 ? 0.0 : 7.99;
            $total    = round($subtotal + $tax + $shipping, 5);

            $addrEntry  = $addrData[$i % $addrCount];

            // Use a temporary order_id; will be replaced after insert with time() . PK
            $tempOrderId = (string) strtotime($createdOn);
            $invoicePrefix = J2CommerceHelper::config()->get('invoice_prefix', 'INV-');

            $order                         = new \stdClass();
            $order->order_id               = $tempOrderId;
            $order->order_type             = 'normal';
            $order->parent_id              = null;
            $order->subscription_id        = null;
            $order->cart_id                = $i + 1;
            $order->invoice_prefix         = $invoicePrefix;
            $order->invoice_number         = $i + 1001;
            $order->token                  = '';
            $order->user_id                = $customer['id'];
            $order->user_email             = $customer['email'];
            $order->order_total            = $total;
            $order->order_subtotal         = $subtotal;
            $order->order_subtotal_ex_tax  = $subtotal;
            $order->order_tax              = $tax;
            $order->order_shipping         = $shipping;
            $order->order_shipping_tax     = 0.0;
            $order->order_discount         = 0.0;
            $order->order_discount_tax     = 0.0;
            $order->order_credit           = 0.0;
            $order->order_refund           = 0.0;
            $order->order_surcharge        = 0.0;
            $order->order_fees             = 0.0;
            $order->orderpayment_type      = 'plg_j2commerce_payment_cod';
            $order->transaction_id         = 'TXN-SAMPLE-' . ($i + 1);
            $order->transaction_status     = 'success';
            $order->transaction_details    = '';
            $order->currency_id            = 1;
            $order->currency_code          = 'USD';
            $order->currency_value         = 1.00000;
            $order->ip_address             = '127.0.0.1';
            $order->is_shippable           = 1;
            $order->is_including_tax       = 0;
            $order->customer_note          = '';
            $order->customer_language      = 'en-GB';
            $order->customer_group         = 'Registered';
            $order->order_state_id         = $statusId;
            $order->order_state            = $statusName;
            $order->order_params           = '{}';
            $order->created_on             = $createdOn;
            $order->created_by             = $customer['id'];
            $order->modified_on            = $createdOn;
            $order->modified_by            = $customer['id'];
            $order->campaign_double_opt_in = null;
            $order->campaign_order_id      = null;

            $db->insertObject('#__j2commerce_orders', $order, 'j2commerce_order_id');
            $pk = (int) $order->j2commerce_order_id;

            // Generate real order_id: timestamp + PK (matches CartOrder / OrderTable pattern)
            $orderId = (string) (strtotime($createdOn) . $pk);
            $orderToken = md5($orderId . bin2hex(random_bytes(8)));

            $updateOrder = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_orders'))
                ->set($db->quoteName('order_id') . ' = :oid')
                ->set($db->quoteName('token') . ' = :token')
                ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':oid', $orderId)
                ->bind(':token', $orderToken)
                ->bind(':pk', $pk, ParameterType::INTEGER);
            $db->setQuery($updateOrder)->execute();

            // Create order info
            $info                     = new \stdClass();
            $info->order_id           = $orderId;
            $info->billing_first_name = $customer['first_name'];
            $info->billing_last_name  = $customer['last_name'];
            $info->billing_phone_1    = $this->randomPhone();
            $info->billing_address_1  = mt_rand(1, 9999) . ' Main St';
            $info->billing_address_2  = '';
            $info->billing_city       = $addrEntry['city'];
            $info->billing_zone_name  = $addrEntry['zone_name'];
            $info->billing_country_name = $addrEntry['country_name'];
            $info->billing_zone_id    = $addrEntry['zone_id'];
            $info->billing_country_id = $addrEntry['country_id'];
            $info->billing_zip        = $addrEntry['zip'];
            $info->billing_company    = '';
            $info->billing_middle_name = '';
            $info->billing_phone_2    = '';
            $info->billing_fax        = '';
            $info->billing_tax_number = '';
            $info->shipping_first_name = $customer['first_name'];
            $info->shipping_last_name  = $customer['last_name'];
            $info->shipping_address_1  = $info->billing_address_1;
            $info->shipping_address_2  = '';
            $info->shipping_city       = $addrEntry['city'];
            $info->shipping_zone_name  = $addrEntry['zone_name'];
            $info->shipping_country_name = $addrEntry['country_name'];
            $info->shipping_zone_id    = $addrEntry['zone_id'];
            $info->shipping_country_id = $addrEntry['country_id'];
            $info->shipping_zip        = $addrEntry['zip'];
            $info->shipping_middle_name = '';
            $info->shipping_phone_1    = $info->billing_phone_1;
            $info->shipping_phone_2    = '';
            $info->shipping_fax        = '';
            $info->shipping_company    = '';
            $info->shipping_id         = '';
            $info->shipping_tax_number = '';
            $info->all_billing         = json_encode(['first_name' => $customer['first_name'], 'last_name' => $customer['last_name']]);
            $info->all_shipping        = json_encode(['first_name' => $customer['first_name'], 'last_name' => $customer['last_name']]);
            $info->all_payment         = '{}';

            $db->insertObject('#__j2commerce_orderinfos', $info);

            // Create order items
            foreach ($orderItems as $k => $item) {
                $oi                                  = new \stdClass();
                $oi->order_id                        = $orderId;
                $oi->orderitem_type                  = 'normal';
                $oi->cart_id                         = $order->cart_id;
                $oi->cartitem_id                     = $k + 1;
                $oi->product_id                      = $item['product_id'];
                $oi->product_type                    = 'simple';
                $oi->variant_id                      = $item['variant_id'];
                $oi->vendor_id                       = 0;
                $oi->orderitem_sku                   = $item['sku'];
                $oi->orderitem_name                  = $item['name'];
                $oi->orderitem_attributes            = '[]';
                $oi->orderitem_quantity              = (string) $item['qty'];
                $oi->orderitem_taxprofile_id         = 0;
                $oi->orderitem_per_item_tax          = round($item['price'] * 0.0825, 5);
                $oi->orderitem_tax                   = round($item['price'] * $item['qty'] * 0.0825, 5);
                $oi->orderitem_discount              = 0.0;
                $oi->orderitem_discount_tax          = 0.0;
                $oi->orderitem_price                 = $item['price'];
                $oi->orderitem_option_price          = 0.0;
                $oi->orderitem_finalprice            = $item['line_total'];
                $oi->orderitem_finalprice_with_tax   = round($item['line_total'] * 1.0825, 5);
                $oi->orderitem_finalprice_without_tax = $item['line_total'];
                $oi->orderitem_params                = '{}';
                $oi->created_on                      = $createdOn;
                $oi->created_by                      = $customer['id'];
                $oi->orderitem_weight                = '0';
                $oi->orderitem_weight_total          = '0';

                $db->insertObject('#__j2commerce_orderitems', $oi);
            }

            // Create order history entry
            $hist                 = new \stdClass();
            $hist->order_id       = $orderId;
            $hist->order_state_id = $statusId;
            $hist->notify_customer = 0;
            $hist->comment        = 'Order created via sample data generator.';
            $hist->created_on     = $createdOn;
            $hist->created_by     = 0;
            $hist->params         = '{}';

            $db->insertObject('#__j2commerce_orderhistories', $hist);

            $created++;
        }

        return $created;
    }

    private function createCoupons(int $count, string $now): int
    {
        $db      = $this->db;
        $data    = array_slice(self::COUPONS_DATA, 0, $count);
        $created = 0;

        $nullDate = '1000-01-01 00:00:00';

        foreach ($data as $i => $couponData) {
            // Check if code already exists
            $code = $couponData['code'];
            $checkQuery = $db->getQuery(true)
                ->select('j2commerce_coupon_id')
                ->from($db->quoteName('#__j2commerce_coupons'))
                ->where($db->quoteName('coupon_code') . ' = :code')
                ->bind(':code', $code);
            $db->setQuery($checkQuery);

            if ($db->loadResult()) {
                continue; // skip existing
            }

            $coupon              = new \stdClass();
            $coupon->coupon_name = '[SAMPLE] ' . $couponData['name'];
            $coupon->coupon_code = $couponData['code'];
            $coupon->enabled     = 1;
            $coupon->ordering    = $i + 1;
            $coupon->value       = $couponData['value'];
            $coupon->value_type  = $couponData['value_type'];
            $coupon->max_value   = '';
            $coupon->free_shipping = $couponData['free_shipping'];
            $coupon->max_uses    = 1000;
            $coupon->logged      = 0;
            $coupon->max_customer_uses = 0;
            $coupon->valid_from  = $nullDate;
            $coupon->valid_to    = $nullDate;
            $coupon->product_category = '';
            $coupon->products    = '';
            $coupon->min_subtotal = $couponData['min_subtotal'];
            $coupon->users       = '';
            $coupon->mycategory  = '';
            $coupon->brand_ids   = '';

            $db->insertObject('#__j2commerce_coupons', $coupon);
            $created++;
        }

        return $created;
    }

    private function createVouchers(int $count, string $now): int
    {
        if ($count < 1) {
            return 0;
        }

        $db      = $this->db;
        $data    = array_slice(self::VOUCHERS_DATA, 0, $count);
        $created = 0;

        $validFrom = date('Y-m-d H:i:s');
        $validTo   = date('Y-m-d H:i:s', strtotime('+1 year'));

        foreach ($data as $i => $voucherData) {
            $code = $voucherData['code'];
            $checkQuery = $db->getQuery(true)
                ->select('j2commerce_voucher_id')
                ->from($db->quoteName('#__j2commerce_vouchers'))
                ->where($db->quoteName('voucher_code') . ' = :code')
                ->bind(':code', $code);
            $db->setQuery($checkQuery);

            if ($db->loadResult()) {
                continue;
            }

            $voucher                = new \stdClass();
            $voucher->order_id      = '0';
            $voucher->email_to      = $voucherData['email_to'];
            $voucher->voucher_code  = $voucherData['code'];
            $voucher->voucher_type  = $voucherData['type'];
            $voucher->subject       = '[SAMPLE] ' . $voucherData['subject'];
            $voucher->email_body    = $voucherData['body'];
            $voucher->valid_from    = $validFrom;
            $voucher->valid_to      = $validTo;
            $voucher->from_order_id = '0';
            $voucher->voucher_value = $voucherData['value'];
            $voucher->ordering      = $i + 1;
            $voucher->enabled       = 1;
            $voucher->access        = 1;
            $voucher->created_on    = $now;
            $voucher->created_by    = 0;
            $voucher->modified_on   = $now;
            $voucher->modified_by   = 0;

            $db->insertObject('#__j2commerce_vouchers', $voucher);
            $created++;
        }

        return $created;
    }

    private function createProductImages(array $productIds, array $catIds): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $db = $this->db;

        // Build a lookup of product_id → category key using the catIds we created
        $catKeyByIndex = [];
        foreach ($catIds as $index => $catEntry) {
            $catKeyByIndex[$index] = $catEntry['key'] ?? 'electronics';
        }

        $imageBase   = 'media/plg_sampledata_j2commerce/images';
        $categories  = ['electronics', 'clothing', 'home', 'sporting', 'books'];
        $imageCount  = 5;
        $created     = 0;

        foreach ($productIds as $index => $productData) {
            $productId = (int) ($productData['id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            // Determine category from rotation
            $catCount = count($catIds);
            $catEntry = $catCount > 0 ? $catIds[$index % $catCount] : null;
            $catKey   = $catEntry['key'] ?? $categories[$index % count($categories)];

            $imageNum  = ($index % $imageCount) + 1;
            $imagePath = $imageBase . '/' . $catKey . '/' . $imageNum . '.svg';

            $img                          = new \stdClass();
            $img->product_id              = $productId;
            $img->main_image              = $imagePath;
            $img->main_image_alt          = '';
            $img->thumb_image             = $imagePath;
            $img->thumb_image_alt         = '';
            $img->tiny_image              = $imagePath;
            $img->tiny_image_alt          = '';
            $img->additional_images       = null;
            $img->additional_images_alt   = null;
            $img->additional_thumb_images = null;
            $img->additional_thumb_images_alt = null;
            $img->additional_tiny_images  = null;
            $img->additional_tiny_images_alt = null;

            $db->insertObject('#__j2commerce_productimages', $img);

            if ((int) $db->insertid() > 0) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Ensure the store has minimal tax, shipping, and payment configured.
     *
     * Checks are additive only — nothing is created if it already exists.
     * These records are NOT tagged as sample data and persist after remove().
     *
     * @return array<string, string>  Human-readable messages about what was done.
     */
    private function ensureStoreSetup(DatabaseInterface $db): array
    {
        $results = [];

        // --- Tax ---
        $taxCheck = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_taxprofiles'));
        $taxCount = (int) $db->setQuery($taxCheck)->loadResult();

        if ($taxCount === 0) {
            OnboardingHelper::createDefaultTax(223, 0, 8.25, $db);
            $results['tax'] = 'Created default US tax profile (8.25%)';
        } else {
            $results['tax'] = 'Tax profile already exists — skipped';
        }

        // --- Shipping ---
        $shipCheck = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_shippingmethods'))
            ->where($db->quoteName('published') . ' = 1');
        $shipCount = (int) $db->setQuery($shipCheck)->loadResult();

        if ($shipCount === 0) {
            // Enable the standard shipping plugin if it exists
            OnboardingHelper::setPluginEnabled('shipping_standard', 'j2commerce', 1, $db);

            // Create a flat-rate shipping method (type 1 = Standard)
            $methodId = OnboardingHelper::createShippingMethod('Standard Shipping', 1, $db);

            if ($methodId > 0) {
                // Create a single worldwide rate at $5.99
                OnboardingHelper::createShippingRates($methodId, [
                    ['geozone_id' => 0, 'price' => 5.99, 'handling' => 0, 'weight_start' => 0, 'weight_end' => 0],
                ], $db);
                $results['shipping'] = 'Created default flat-rate shipping method ($5.99)';
            } else {
                $results['shipping'] = 'Could not create shipping method';
            }
        } else {
            $results['shipping'] = 'Shipping method already exists — skipped';
        }

        // --- Payment ---
        $payCheck = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' LIKE ' . $db->quote('payment_%'))
            ->where($db->quoteName('enabled') . ' = 1');
        $payCount = (int) $db->setQuery($payCheck)->loadResult();

        if ($payCount === 0) {
            OnboardingHelper::setPluginEnabled('payment_cash', 'j2commerce', 1, $db);
            $results['payment'] = 'Enabled cash-on-delivery payment plugin';
        } else {
            $results['payment'] = 'Payment plugin already enabled — skipped';
        }

        return $results;
    }

    /**
     * Create advanced / sale pricing for a random selection of products.
     *
     * Used only for the "full" profile to showcase advanced pricing features.
     *
     * @param  array<array{id: int, variant_id: int, price: float, ...}>  $productIds
     * @return int  Number of price rows created.
     */
    private function createAdvancedPricing(array $productIds, DatabaseInterface $db): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $created  = 0;
        $total    = count($productIds);

        // Pick 5 products (or fewer if not enough exist)
        $pickCount = min(5, $total);
        $indices   = array_rand($productIds, $pickCount);

        if (!is_array($indices)) {
            $indices = [$indices];
        }

        $selected = array_map(fn($i) => $productIds[$i], $indices);

        $now      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dateFrom = $now->modify('-30 days')->format('Y-m-d H:i:s');
        $dateTo   = $now->modify('+30 days')->format('Y-m-d H:i:s');

        foreach ($selected as $k => $product) {
            $variantId = (int) ($product['variant_id'] ?? 0);
            $basePrice = (float) ($product['price'] ?? 0.0);

            if ($variantId <= 0 || $basePrice <= 0.0) {
                continue;
            }

            if ($k < 3) {
                // Sale / special price: 20% off, active now
                $salePrice = round($basePrice * 0.80, 5);

                $row                  = new \stdClass();
                $row->variant_id      = $variantId;
                $row->quantity_from   = null;
                $row->quantity_to     = null;
                $row->date_from       = $dateFrom;
                $row->date_to         = $dateTo;
                $row->customer_group_id = null;
                $row->price           = number_format($salePrice, 5, '.', '');
                $row->params          = null;

                $db->insertObject('#__j2commerce_product_prices', $row);
                $created++;
            } else {
                // Quantity-based tier pricing
                $tiers = [
                    ['qty_from' => 5.00000,  'qty_to' => 9.00000,  'discount' => 0.10],
                    ['qty_from' => 10.00000, 'qty_to' => 24.00000, 'discount' => 0.15],
                    ['qty_from' => 25.00000, 'qty_to' => null,     'discount' => 0.20],
                ];

                foreach ($tiers as $tier) {
                    $tierPrice = round($basePrice * (1.0 - $tier['discount']), 5);
                    $qtyFrom   = number_format($tier['qty_from'], 5, '.', '');
                    $qtyTo     = $tier['qty_to'] !== null
                        ? number_format($tier['qty_to'], 5, '.', '')
                        : null;

                    $row                  = new \stdClass();
                    $row->variant_id      = $variantId;
                    $row->quantity_from   = $qtyFrom;
                    $row->quantity_to     = $qtyTo;
                    $row->date_from       = null;
                    $row->date_to         = null;
                    $row->customer_group_id = null;
                    $row->price           = number_format($tierPrice, 5, '.', '');
                    $row->params          = null;

                    $db->insertObject('#__j2commerce_product_prices', $row);
                    $created++;
                }
            }
        }

        return $created;
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    private function buildVariantObject(int $productId, string $sku, float $price, string $now, bool $isMaster): \stdClass
    {
        $v                         = new \stdClass();
        $v->product_id             = $productId;
        $v->is_master              = $isMaster ? 1 : 0;
        $v->sku                    = $sku;
        $v->upc                    = '';
        $v->price                  = $price;
        $v->pricing_calculator     = 'standardprice';
        $v->shipping               = 1;
        $v->params                 = '{}';
        $v->length                 = 0.0;
        $v->width                  = 0.0;
        $v->height                 = 0.0;
        $v->length_class_id        = 0;
        $v->weight                 = 0.0;
        $v->weight_class_id        = 0;
        $v->created_on             = $now;
        $v->created_by             = 0;
        $v->modified_on            = $now;
        $v->modified_by            = 0;
        $v->manage_stock           = 1;
        $v->quantity_restriction   = 0;
        $v->min_out_qty            = 0.0;
        $v->use_store_config_min_out_qty  = 1;
        $v->min_sale_qty           = 0.0;
        $v->use_store_config_min_sale_qty = 1;
        $v->max_sale_qty           = 0.0;
        $v->use_store_config_max_sale_qty = 1;
        $v->notify_qty             = 0.0;
        $v->use_store_config_notify_qty   = 1;
        $v->availability           = 0;
        $v->sold                   = 0.0;
        $v->allow_backorder        = 0;
        $v->isdefault_variant      = $isMaster ? 1 : 0;

        return $v;
    }

    private function buildNamePool(): array
    {
        $pool = [];
        foreach (self::PRODUCT_NAMES as $key => $names) {
            $pool[$key] = $names;
        }
        return $pool;
    }

    private function uniqueAlias(string $base, string $table): string
    {
        $db      = $this->db;
        $alias   = $base;
        $counter = 1;

        while (true) {
            $testAlias = $alias;
            $query     = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__' . $table))
                ->where($db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $testAlias);
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                return $alias;
            }

            $alias = $base . '-' . $counter;
            $counter++;
        }
    }

    private function randomStreetName(): string
    {
        $names = ['Oak', 'Maple', 'Cedar', 'Pine', 'Elm', 'Washington', 'Lincoln', 'Jefferson', 'Madison', 'Franklin'];
        return $names[array_rand($names)];
    }

    private function randomStreetType(): string
    {
        $types = ['St', 'Ave', 'Blvd', 'Dr', 'Ln', 'Ct', 'Way', 'Pl'];
        return $types[array_rand($types)];
    }

    private function randomPhone(): string
    {
        return sprintf('+1-%d-%d-%d', mt_rand(200, 999), mt_rand(200, 999), mt_rand(1000, 9999));
    }
}
