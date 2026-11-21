<?php
declare(strict_types=1);

abstract class BookingPlan
{
    protected string $name;

    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function create(string $tourType): BookingPlan
    {
        return match ($tourType) {
            'Day Tour' => new DayTourPlan(),
            'Overnight Tour' => new OvernightTourPlan(),
            default => throw new InvalidArgumentException('Invalid tour type.'),
        };
    }

    public function getName(): string
    {
        return $this->name;
    }

    abstract public function normalizeStayDates(string $checkIn, string $checkOut): array;
    abstract public function validateStayDates(?DateTimeImmutable $checkInDate, ?DateTimeImmutable $checkOutDate): array;
}

final class DayTourPlan extends BookingPlan
{
    public function __construct()
    {
        parent::__construct('Day Tour');
    }

    public function normalizeStayDates(string $checkIn, string $checkOut): array
    {
        return [$checkIn, $checkIn];
    }

    public function validateStayDates(?DateTimeImmutable $checkInDate, ?DateTimeImmutable $checkOutDate): array
    {
        if ($checkInDate && $checkOutDate && $checkInDate != $checkOutDate) {
            return ['Day tours must check out on the same date.'];
        }

        return [];
    }
}

final class OvernightTourPlan extends BookingPlan
{
    public function __construct()
    {
        parent::__construct('Overnight Tour');
    }

    public function normalizeStayDates(string $checkIn, string $checkOut): array
    {
        return [$checkIn, $checkOut];
    }

    public function validateStayDates(?DateTimeImmutable $checkInDate, ?DateTimeImmutable $checkOutDate): array
    {
        if ($checkInDate && $checkOutDate && $checkOutDate <= $checkInDate) {
            return ['Overnight tours must check out after check-in.'];
        }

        return [];
    }
}

final class BookingRequest
{
    private string $name;
    private string $email;
    private string $phone;
    private string $pool;
    private string $tourType;
    private string $checkIn;
    private string $checkOut;
    private string $checkInTime;
    private string $checkOutTime;

    public function __construct(
        string $name,
        string $email,
        string $phone,
        string $pool,
        string $tourType,
        string $checkIn,
        string $checkOut,
        string $checkInTime,
        string $checkOutTime
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->pool = $pool;
        $this->tourType = $tourType;
        $this->checkIn = $checkIn;
        $this->checkOut = $checkOut;
        $this->checkInTime = $checkInTime;
        $this->checkOutTime = $checkOutTime;
    }

    public function toDatabaseArray(): array
    {
        return [
            'full_name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'pool_size' => $this->pool,
            'tour_type' => $this->tourType,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'check_in_time' => $this->checkInTime,
            'check_out_time' => $this->checkOutTime,
        ];
    }
}

final class BookingRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function getConfirmedBookings(): array
    {
        return $this->connection->query(
            "SELECT pool_size, check_in, check_out
             FROM bookings
             WHERE status = 'Confirmed'
               AND check_out >= CURDATE()
             ORDER BY check_in"
        )->fetchAll();
    }

    public function save(BookingRequest $booking): void
    {
        $data = $booking->toDatabaseArray();
        $this->connection->beginTransaction();

        try {
            $customerStatement = $this->connection->prepare(
                'INSERT INTO customers (full_name, email, phone)
                 VALUES (:full_name, :email, :phone)
                 ON DUPLICATE KEY UPDATE
                    CustomerID = LAST_INSERT_ID(CustomerID),
                    full_name = VALUES(full_name),
                    phone = VALUES(phone)'
            );
            $customerStatement->execute([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);
            $customerId = (int) $this->connection->lastInsertId();

            $statement = $this->connection->prepare(
                'INSERT INTO bookings (CustomerID, full_name, email, phone, pool_size, tour_type, check_in, check_out, check_in_time, check_out_time)
                 VALUES (:CustomerID, :full_name, :email, :phone, :pool_size, :tour_type, :check_in, :check_out, :check_in_time, :check_out_time)'
            );
            $statement->execute(['CustomerID' => $customerId] + $data);

            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }
}
