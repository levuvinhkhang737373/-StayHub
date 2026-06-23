<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterDevice extends Model
{
    use HasFactory;


    public const METER_TYPE_ELECTRIC = 1;
    public const METER_TYPE_WATER = 2;

    public const METER_TYPE_LABELS = [
        self::METER_TYPE_ELECTRIC => 'Điện',
        self::METER_TYPE_WATER => 'Nước',
    ];

    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;
    public const STATUS_REPLACED = 3;
    public const STATUS_BROKEN = 4;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Đang sử dụng',
        self::STATUS_INACTIVE => 'Ngừng sử dụng',
        self::STATUS_REPLACED => 'Đã bị thay thế',
        self::STATUS_BROKEN => 'Bị hỏng',
    ];

    protected $fillable = ['room_id', 'service_id', 'meter_code', 'meter_type', 'initial_reading', 'installed_at', 'replaced_by_meter_id', 'status', 'image_path', 'note'];

    protected static function booted(): void
    {
        static::saving(function (MeterDevice $meterDevice) {
            if (empty($meterDevice->meter_code)) {
                $meterDevice->meter_code = static::generateMeterCode(
                    (int) $meterDevice->room_id,
                    (int) $meterDevice->meter_type
                );
            }
        });
    }

    public static function getBuildingAbbreviation(?Building $building): string
    {
        if (!$building) {
            return 'BLD';
        }

        // Convert to ASCII and remove non-alphanumeric/non-space/non-dash characters
        $asciiName = \Illuminate\Support\Str::ascii($building->name);
        $cleanName = preg_replace('/[^A-Za-z0-9\s\-]/', '', $asciiName);
        
        // Split by spaces or dashes and filter empty values
        $words = array_values(array_filter(preg_split('/[\s\-]+/', $cleanName)));
        
        // Remove "StayHub" (case-insensitive) if it is the first word
        if (!empty($words) && strtolower($words[0]) === 'stayhub') {
            array_shift($words);
        }

        if (empty($words)) {
            return 'BLD';
        }

        $wordCount = count($words);
        if ($wordCount >= 3) {
            // Take first letter of first 3 words
            $char1 = substr($words[0], 0, 1);
            $char2 = substr($words[1], 0, 1);
            $char3 = substr($words[2], 0, 1);
            $result = $char1 . $char2 . $char3;
        } elseif ($wordCount === 2) {
            // Take first 2 letters of first word + first letter of second word
            $char1 = substr($words[0], 0, 2);
            $char2 = substr($words[1], 0, 1);
            $result = $char1 . $char2;
        } else {
            // Take first 3 letters of the single word
            $result = substr($words[0], 0, 3);
        }

        return str_pad(strtoupper($result), 3, 'X');
    }

    public static function generateMeterCode(int $roomId, int $meterType): string
    {
        $room = Room::with('building')->find($roomId);
        if (!$room) {
            return '';
        }

        $building = $room->building;
        $buildingPart = static::getBuildingAbbreviation($building);
        $roomPart = $room->room_number;
        $typePart = $meterType === self::METER_TYPE_ELECTRIC ? 'ĐHĐ' : 'ĐHN';

        $count = static::where('room_id', $roomId)
            ->where('meter_type', $meterType)
            ->count();

        return "{$buildingPart}-{$roomPart}-{$typePart}-{$count}";
    }

    protected function casts(): array
    {
        return ['meter_type' => 'integer',
            'initial_reading' => 'decimal:2',
            'status' => 'integer', 'installed_at' => 'date'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function replacementMeter(): BelongsTo
    {
        return $this->belongsTo(MeterDevice::class, 'replaced_by_meter_id');
    }

    public function replacedMeters(): HasMany
    {
        return $this->hasMany(MeterDevice::class, 'replaced_by_meter_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }
}
