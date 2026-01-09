<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Room;
use App\Services\Calendar\AvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionDataSeeder extends Seeder
{
    /**
     * Заповнює систему тестовими даними для демонстрації:
     * - Записи на 3-4 місяці вперед (5-6 на день для активних лікарів)
     * - Склад 20-30 позицій з різними залишками та цінами
     * - Фінанси 20-30 рахунків з різними статусами
     * - Оновлює адреси та дані існуючих клінік
     */
    public function run(): void
    {
        $this->command->info('Оновлення даних клінік...');
        $this->updateClinics();

        $this->command->info('Створення записів на 3-4 місяці вперед...');
        $this->seedFutureAppointments();

        $this->command->info('Заповнення складу...');
        $this->seedInventory();

        $this->command->info('Створення рахунків...');
        $this->seedInvoices();

        $this->command->info('Готово!');
    }

    /**
     * Оновлює адреси та дані існуючих клінік
     */
    private function updateClinics(): void
    {
        $clinicsData = [
            [
                'city' => 'Київ',
                'address_street' => 'Хрещатик',
                'address_building' => '25',
                'phone_main' => '+380442345678',
                'email_public' => 'info@dentalkiev.com',
                'website' => 'https://dentalkiev.com',
                'slogan' => 'Ваша посмішка - наша місія',
                'requisites' => [
                    'legal_name' => 'ТОВ "Дентал-Київ"',
                    'tax_id' => '12345678',
                    'iban' => 'UA123456789012345678901234567',
                    'bank_name' => 'ПриватБанк',
                    'mfo' => '305299',
                ],
            ],
            [
                'city' => 'Львів',
                'address_street' => 'Проспект Свободи',
                'address_building' => '15',
                'phone_main' => '+380322345678',
                'email_public' => 'info@dentallviv.com',
                'website' => 'https://dentallviv.com',
                'slogan' => 'Професійна стоматологія у Львові',
                'requisites' => [
                    'legal_name' => 'ТОВ "Дентал-Львів"',
                    'tax_id' => '23456789',
                    'iban' => 'UA234567890123456789012345678',
                    'bank_name' => 'Райффайзен Банк',
                    'mfo' => '380805',
                ],
            ],
            [
                'city' => 'Одеса',
                'address_street' => 'Дерибасівська',
                'address_building' => '10',
                'phone_main' => '+380482345678',
                'email_public' => 'info@dentalodessa.com',
                'website' => 'https://dentalodessa.com',
                'slogan' => 'Здорові зуби - красива посмішка',
                'requisites' => [
                    'legal_name' => 'ФОП Іванов Іван Іванович',
                    'tax_id' => '34567890',
                    'iban' => 'UA345678901234567890123456789',
                    'bank_name' => 'Монобанк',
                    'mfo' => null,
                ],
            ],
            [
                'city' => 'Харків',
                'address_street' => 'Сумська',
                'address_building' => '30',
                'phone_main' => '+380572345678',
                'email_public' => 'info@dentalkharkiv.com',
                'website' => 'https://dentalkharkiv.com',
                'slogan' => 'Сучасна стоматологія для всієї родини',
                'requisites' => [
                    'legal_name' => 'ТОВ "Дентал-Харків"',
                    'tax_id' => '45678901',
                    'iban' => 'UA456789012345678901234567890',
                    'bank_name' => 'УКРСИББАНК',
                    'mfo' => '351005',
                ],
            ],
            [
                'city' => 'Дніпро',
                'address_street' => 'Набережна Перемоги',
                'address_building' => '5',
                'phone_main' => '+380562345678',
                'email_public' => 'info@dentaldnipro.com',
                'website' => 'https://dentaldnipro.com',
                'slogan' => 'Індивідуальний підхід до кожного пацієнта',
                'requisites' => [
                    'legal_name' => 'ТОВ "Дентал-Дніпро"',
                    'tax_id' => '56789012',
                    'iban' => 'UA567890123456789012345678901',
                    'bank_name' => 'Ощадбанк',
                    'mfo' => '300465',
                ],
            ],
        ];

        $clinics = Clinic::all();

        foreach ($clinics as $index => $clinic) {
            if (isset($clinicsData[$index])) {
                $clinic->update($clinicsData[$index]);
                $this->command->info("Оновлено клініку: {$clinic->name}");
            } else {
                // Для додаткових клінік використовуємо випадкові дані
                $clinic->update([
                    'city' => fake()->city(),
                    'address_street' => fake()->streetName(),
                    'address_building' => fake()->buildingNumber(),
                    'phone_main' => '+380' . fake()->numberBetween(10, 99) . fake()->numberBetween(1000000, 9999999),
                    'email_public' => fake()->companyEmail(),
                    'website' => 'https://' . fake()->domainName(),
                    'slogan' => fake()->optional()->sentence(),
                    'requisites' => [
                        'legal_name' => fake()->company(),
                        'tax_id' => fake()->numerify('########'),
                        'iban' => 'UA' . fake()->numerify('###########################'),
                        'bank_name' => fake()->randomElement(['ПриватБанк', 'Райффайзен Банк', 'Монобанк', 'Ощадбанк']),
                        'mfo' => fake()->optional()->numerify('######'),
                    ],
                ]);
            }
        }
    }

    /**
     * Створює записи на 3-4 місяці вперед для активних лікарів (5-6 записів на день)
     */
    private function seedFutureAppointments(): void
    {
        $availability = new AvailabilityService();

        $patients = Patient::all();
        if ($patients->isEmpty()) {
            $patients = Patient::factory(50)->create();
        }

        $procedures = Procedure::all();
        if ($procedures->isEmpty()) {
            $this->command->warn('Немає процедур для записів. Створіть спочатку процедури.');
            return;
        }

        $startDate = Carbon::today()->addDay(); // З завтра
        $endDate = $startDate->copy()->addMonths(4); // 4 місяці вперед
        $period = new CarbonPeriod($startDate, $endDate);

        $activeDoctors = Doctor::where('is_active', true)
            ->with(['clinics', 'appointments'])
            ->get();

        if ($activeDoctors->isEmpty()) {
            $this->command->warn('Немає активних лікарів. Записи не створено.');
            return;
        }

        $createdCount = 0;

        foreach ($activeDoctors as $doctor) {
            $clinicIds = collect([$doctor->clinic_id])
                ->merge($doctor->clinics?->pluck('id') ?? [])
                ->filter()
                ->unique()
                ->values();

            if ($clinicIds->isEmpty()) {
                continue;
            }

            foreach ($period as $date) {
                // Пропускаємо вихідні (субота та неділя)
                if ($date->isWeekend()) {
                    continue;
                }

                foreach ($clinicIds as $clinicId) {
                    $plan = $availability->getDailyPlan($doctor, $date, $clinicId);
                    if (isset($plan['reason'])) {
                        continue;
                    }

                    $slotDuration = $plan['slot_duration'] ?? 30;
                    $slots = $availability->getSlots(
                        $doctor,
                        $date,
                        $slotDuration,
                        null,
                        null,
                        null,
                        null,
                        $clinicId
                    )['slots'] ?? [];

                    if (empty($slots)) {
                        continue;
                    }

                    // 5-6 записів на день
                    $appointmentsPerDay = fake()->numberBetween(5, 6);
                    $daySlots = collect($slots)->shuffle()->take($appointmentsPerDay);

                    foreach ($daySlots as $slot) {
                        $startAt = Carbon::parse($date->toDateString() . ' ' . $slot['start']);
                        $endAt = Carbon::parse($date->toDateString() . ' ' . $slot['end']);

                        // Перевірка на дублікати
                        if (
                            Appointment::where('doctor_id', $doctor->id)
                                ->where('start_at', $startAt)
                                ->exists()
                        ) {
                            continue;
                        }

                        $patient = $patients->random();
                        $procedure = $procedures
                            ->where('clinic_id', $clinicId)
                            ->whenEmpty(fn ($c) => $procedures)
                            ->random();

                        $room = Room::where('clinic_id', $clinicId)->inRandomOrder()->first();

                        Appointment::create([
                            'clinic_id' => $clinicId,
                            'doctor_id' => $doctor->id,
                            'patient_id' => $patient->id,
                            'procedure_id' => $procedure?->id,
                            'room_id' => $room?->id,
                            'equipment_id' => $procedure?->equipment_id,
                            'assistant_id' => null,
                            'start_at' => $startAt,
                            'end_at' => $endAt,
                            'status' => Arr::random(['planned', 'confirmed']),
                            'source' => Arr::random(['phone', 'site', 'in_person']),
                            'comment' => fake()->optional(0.3)->sentence(),
                            'is_follow_up' => fake()->boolean(15),
                        ]);

                        $createdCount++;
                    }
                }
            }
        }

        $this->command->info("Створено {$createdCount} записів на майбутнє.");
    }

    /**
     * Заповнює склад 20-30 позиціями з різними залишками та цінами
     */
    private function seedInventory(): void
    {
        $clinics = Clinic::all();

        if ($clinics->isEmpty()) {
            $this->command->warn('Немає клінік. Склад не заповнено.');
            return;
        }

        $inventoryItems = [
            ['name' => 'Рукавички медичні одноразові M', 'unit' => 'шт', 'min_stock' => 100, 'current_stock' => 500, 'price' => 2.50],
            ['name' => 'Рукавички медичні одноразові L', 'unit' => 'шт', 'min_stock' => 100, 'current_stock' => 450, 'price' => 2.50],
            ['name' => 'Масці медичні одноразові', 'unit' => 'шт', 'min_stock' => 200, 'current_stock' => 800, 'price' => 5.00],
            ['name' => 'Халати медичні одноразові', 'unit' => 'шт', 'min_stock' => 50, 'current_stock' => 120, 'price' => 45.00],
            ['name' => 'Анестезія Ультракаїн', 'unit' => 'мл', 'min_stock' => 10, 'current_stock' => 35, 'price' => 150.00],
            ['name' => 'Анестезія Септанест', 'unit' => 'мл', 'min_stock' => 10, 'current_stock' => 28, 'price' => 120.00],
            ['name' => 'Стерилізаційні пакети', 'unit' => 'шт', 'min_stock' => 500, 'current_stock' => 2000, 'price' => 0.50],
            ['name' => 'Індикатори стерилізації', 'unit' => 'шт', 'min_stock' => 50, 'current_stock' => 150, 'price' => 8.00],
            ['name' => 'Вата стерильна', 'unit' => 'кг', 'min_stock' => 2, 'current_stock' => 8, 'price' => 180.00],
            ['name' => 'Марля медична', 'unit' => 'м', 'min_stock' => 10, 'current_stock' => 35, 'price' => 25.00],
            ['name' => 'Бінти еластичні', 'unit' => 'шт', 'min_stock' => 20, 'current_stock' => 60, 'price' => 35.00],
            ['name' => 'Шовний матеріал 3-0', 'unit' => 'шт', 'min_stock' => 5, 'current_stock' => 15, 'price' => 280.00],
            ['name' => 'Шовний матеріал 4-0', 'unit' => 'шт', 'min_stock' => 5, 'current_stock' => 18, 'price' => 280.00],
            ['name' => 'Ігли ін\'єкційні одноразові', 'unit' => 'шт', 'min_stock' => 100, 'current_stock' => 400, 'price' => 1.50],
            ['name' => 'Шприци одноразові 2мл', 'unit' => 'шт', 'min_stock' => 100, 'current_stock' => 350, 'price' => 3.00],
            ['name' => 'Шприци одноразові 5мл', 'unit' => 'шт', 'min_stock' => 50, 'current_stock' => 180, 'price' => 3.50],
            ['name' => 'Алгінат для відтисків', 'unit' => 'кг', 'min_stock' => 1, 'current_stock' => 3, 'price' => 450.00],
            ['name' => 'Сільвет для відтисків', 'unit' => 'кг', 'min_stock' => 1, 'current_stock' => 2, 'price' => 520.00],
            ['name' => 'Гіпс для моделей', 'unit' => 'кг', 'min_stock' => 5, 'current_stock' => 20, 'price' => 80.00],
            ['name' => 'Кам\'яний гіпс', 'unit' => 'кг', 'min_stock' => 3, 'current_stock' => 12, 'price' => 120.00],
            ['name' => 'Пломбувальний матеріал композит', 'unit' => 'г', 'min_stock' => 50, 'current_stock' => 200, 'price' => 850.00],
            ['name' => 'Склоіономерний цемент', 'unit' => 'г', 'min_stock' => 30, 'current_stock' => 100, 'price' => 650.00],
            ['name' => 'Абразивні диски', 'unit' => 'шт', 'min_stock' => 20, 'current_stock' => 75, 'price' => 45.00],
            ['name' => 'Абразивні головки', 'unit' => 'шт', 'min_stock' => 30, 'current_stock' => 90, 'price' => 35.00],
            ['name' => 'Дезинфекційний розчин', 'unit' => 'л', 'min_stock' => 5, 'current_stock' => 18, 'price' => 280.00],
            ['name' => 'Антисептик для рук', 'unit' => 'л', 'min_stock' => 3, 'current_stock' => 10, 'price' => 150.00],
            ['name' => 'Одноразові стаканчики', 'unit' => 'шт', 'min_stock' => 500, 'current_stock' => 2000, 'price' => 0.30],
            ['name' => 'Слюноотсос одноразовий', 'unit' => 'шт', 'min_stock' => 200, 'current_stock' => 600, 'price' => 1.20],
            ['name' => 'Наконечники для бормашини', 'unit' => 'шт', 'min_stock' => 10, 'current_stock' => 25, 'price' => 850.00],
            ['name' => 'Фторидний гель', 'unit' => 'г', 'min_stock' => 50, 'current_stock' => 150, 'price' => 420.00],
        ];

        $totalItems = fake()->numberBetween(20, 30);
        $selectedItems = collect($inventoryItems)->shuffle()->take($totalItems);

        foreach ($clinics as $clinic) {
            foreach ($selectedItems as $itemData) {
                // Випадковий код/артикул
                $code = 'MAT-' . fake()->unique()->numberBetween(1000, 9999);

                // Створюємо матеріал з початковим залишком 0, потім встановимо через транзакцію
                $initialQuantity = $itemData['current_stock'];
                $inventoryItem = InventoryItem::create([
                    'clinic_id' => $clinic->id,
                    'name' => $itemData['name'],
                    'code' => $code,
                    'unit' => $itemData['unit'],
                    'current_stock' => 0, // Спочатку 0, встановимо через транзакцію adjustment
                    'min_stock_level' => $itemData['min_stock'],
                    'is_active' => true,
                ]);

                // Створюємо транзакцію "adjustment" для початкового залишку (adjustment додає до current_stock)
                InventoryTransaction::create([
                    'clinic_id' => $clinic->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'type' => InventoryTransaction::TYPE_ADJUSTMENT,
                    'quantity' => number_format($initialQuantity, 3, '.', ''),
                    'cost_per_unit' => null,
                    'note' => 'Початковий залишок',
                    'created_by' => null,
                ]);
                // Оновлюємо залишок після adjustment (adjustment додає quantity)
                $inventoryItem->current_stock = number_format($initialQuantity, 3, '.', '');
                $inventoryItem->save();

                // Додаємо випадкові транзакції для історії (в минулому)
                $transactionsCount = fake()->numberBetween(2, 5);
                $currentStock = (float) $initialQuantity;
                
                for ($i = 0; $i < $transactionsCount; $i++) {
                    $transactionType = fake()->randomElement([
                        InventoryTransaction::TYPE_PURCHASE,
                        InventoryTransaction::TYPE_USAGE,
                    ]);

                    $quantity = $transactionType === InventoryTransaction::TYPE_PURCHASE
                        ? fake()->numberBetween(10, 100)
                        : min(fake()->numberBetween(5, 50), (int) $currentStock); // Не більше поточного залишку

                    if ($quantity <= 0) {
                        continue;
                    }

                    InventoryTransaction::create([
                        'clinic_id' => $clinic->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'type' => $transactionType,
                        'quantity' => number_format($quantity, 3, '.', ''),
                        'cost_per_unit' => $transactionType === InventoryTransaction::TYPE_PURCHASE ? number_format($itemData['price'], 2, '.', '') : null,
                        'note' => fake()->randomElement([
                            'Поставка від постачальника',
                            'Використано для пацієнта',
                            'Регулярна поставка',
                            'Списано через пошкодження',
                            'Використано на процедурі',
                        ]),
                        'created_by' => null,
                        'created_at' => fake()->dateTimeBetween('-60 days', '-1 day'),
                        'updated_at' => fake()->dateTimeBetween('-60 days', '-1 day'),
                    ]);

                    // Оновлюємо поточний залишок для наступних транзакцій
                    if ($transactionType === InventoryTransaction::TYPE_PURCHASE) {
                        $currentStock += $quantity;
                    } else {
                        $currentStock = max(0, $currentStock - $quantity);
                    }
                }

                // Оновлюємо фінальний залишок після всіх транзакцій
                $inventoryItem->current_stock = number_format($currentStock, 3, '.', '');
                $inventoryItem->save();
            }

            fake()->unique(true); // Reset unique для кожної клініки
        }

        $this->command->info("Створено позицій на складі для кожної клініки.");
    }

    /**
     * Створює 20-30 рахунків з різними статусами
     */
    private function seedInvoices(): void
    {
        $clinics = Clinic::all();
        $patients = Patient::all();
        $procedures = Procedure::all();

        if ($clinics->isEmpty() || $patients->isEmpty() || $procedures->isEmpty()) {
            $this->command->warn('Немає достатньо даних для створення рахунків (клініки, пацієнти або процедури).');
            return;
        }

        $invoicesCount = fake()->numberBetween(20, 30);
        $statuses = [
            Invoice::STATUS_UNPAID => 8, // 8 рахунків не оплачено
            Invoice::STATUS_PARTIALLY_PAID => 6, // 6 частково оплачено
            Invoice::STATUS_PAID => 12, // 12 оплачено
            Invoice::STATUS_CANCELLED => 2, // 2 скасовано
        ];

        $createdCount = 0;

        foreach ($clinics as $clinic) {
            foreach ($statuses as $status => $count) {
                for ($i = 0; $i < $count && $createdCount < $invoicesCount; $i++) {
                    $patient = $patients->where('clinic_id', $clinic->id)->whenEmpty(fn ($c) => $patients)->random();
                    $selectedProcedures = $procedures
                        ->where('clinic_id', $clinic->id)
                        ->whenEmpty(fn ($c) => $procedures)
                        ->shuffle()
                        ->take(fake()->numberBetween(1, 4));

                    if ($selectedProcedures->isEmpty()) {
                        continue;
                    }

                    // Розраховуємо суму рахунка
                    $amount = 0;
                    $items = [];

                    foreach ($selectedProcedures as $procedure) {
                        $quantity = fake()->numberBetween(1, 3);
                        $price = $procedure->price ?? fake()->randomFloat(2, 300, 5000);
                        $total = $quantity * $price;

                        $items[] = [
                            'procedure_id' => $procedure->id,
                            'name' => $procedure->name,
                            'quantity' => $quantity,
                            'price' => $price,
                            'total' => $total,
                        ];

                        $amount += $total;
                    }

                    // Знижка (опціонально)
                    $discountType = fake()->optional(0.4)->randomElement(['percent', 'amount']);
                    $discountAmount = 0;
                    if ($discountType) {
                        if ($discountType === 'percent') {
                            $discountPercent = fake()->numberBetween(5, 20);
                            $discountAmount = round($amount * $discountPercent / 100, 2);
                        } else {
                            $discountAmount = fake()->randomFloat(2, 50, 500);
                        }
                        $amount = max(0, $amount - $discountAmount);
                    }

                    // Визначаємо оплачену суму залежно від статусу
                    $paidAmount = 0;
                    $paidAt = null;

                    if ($status === Invoice::STATUS_PAID) {
                        $paidAmount = $amount;
                        $paidAt = fake()->dateTimeBetween('-30 days', 'now');
                    } elseif ($status === Invoice::STATUS_PARTIALLY_PAID) {
                        $paidAmount = fake()->randomFloat(2, $amount * 0.3, $amount * 0.7);
                        $paidAt = fake()->dateTimeBetween('-20 days', 'now');
                    }

                    $dueDate = fake()->dateTimeBetween('now', '+30 days');
                    $invoiceDate = fake()->dateTimeBetween('-30 days', 'now');

                    $invoice = Invoice::create([
                        'clinic_id' => $clinic->id,
                        'patient_id' => $patient->id,
                        'appointment_id' => null,
                        'invoice_number' => $this->generateInvoiceNumber($invoiceDate),
                        'amount' => round($amount, 2),
                        'discount_amount' => round($discountAmount, 2),
                        'discount_type' => $discountType,
                        'paid_amount' => round($paidAmount, 2),
                        'status' => $status,
                        'is_prepayment' => fake()->boolean(10),
                        'description' => fake()->optional(0.3)->sentence(),
                        'due_date' => $dueDate,
                        'paid_at' => $paidAt,
                        'created_at' => $invoiceDate,
                        'updated_at' => $invoiceDate,
                    ]);

                    // Створюємо позиції рахунка
                    foreach ($items as $itemData) {
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'procedure_id' => $itemData['procedure_id'],
                            'name' => $itemData['name'],
                            'quantity' => $itemData['quantity'],
                            'price' => $itemData['price'],
                            'total' => $itemData['total'],
                            'created_at' => $invoiceDate,
                            'updated_at' => $invoiceDate,
                        ]);
                    }

                    // Створюємо платежі для оплачених та частково оплачених рахунків
                    if ($status === Invoice::STATUS_PAID || $status === Invoice::STATUS_PARTIALLY_PAID) {
                        $paymentMethods = ['cash', 'card', 'bank_transfer'];
                        $paymentMethod = Arr::random($paymentMethods);

                        Payment::create([
                            'clinic_id' => $clinic->id,
                            'invoice_id' => $invoice->id,
                            'amount' => round($paidAmount, 2),
                            'method' => $paymentMethod,
                            'transaction_id' => $paymentMethod === 'bank_transfer' ? fake()->numerify('TR-########') : null,
                            'created_by' => null,
                            'is_refund' => false,
                            'created_at' => $paidAt ?? $invoiceDate,
                            'updated_at' => $paidAt ?? $invoiceDate,
                        ]);
                    }

                    $createdCount++;
                }
            }
        }

        $this->command->info("Створено {$createdCount} рахунків з різними статусами.");
    }

    /**
     * Генерує унікальний номер рахунка
     */
    private function generateInvoiceNumber($date = null): string
    {
        $date = $date ? Carbon::instance($date) : Carbon::now();
        $random = Str::upper(Str::random(4));
        $timestamp = $date->format('YmdHis') . fake()->numberBetween(100, 999);
        
        return 'INV-' . $timestamp . '-' . $random;
    }
}
