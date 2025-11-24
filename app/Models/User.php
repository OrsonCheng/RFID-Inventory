<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
// ★★★ 1. 必須引入 Sanctum 的 HasApiTokens ★★★
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * 指定這個模型使用的 guard（對應 Spatie 權限系統）
     */
    protected $guard_name = 'web';

    /**
     * 可批量填充的欄位
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'team_id',
        'position',
        'is_active',
    ];

    /**
     * 序列化時要隱藏的欄位
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 資料型態轉換設定
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * ----------------------------------------------------------
     * 關聯定義區
     * ----------------------------------------------------------
     */

    /**
     * 使用者所屬的團隊
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * 使用者的部門（透過團隊）
     */
    public function department()
    {
        return $this->hasOneThrough(
            Department::class, // 最終目標模型
            Team::class,       // 中介模型
            'id',              // Team 的主鍵
            'id',              // Department 的主鍵
            'team_id',         // User 表的外鍵
            'department_id'    // Team 表的外鍵
        );
    }

    /**
     * ----------------------------------------------------------
     * 權限與角色輔助方法
     * ----------------------------------------------------------
     */

    /**
     * 取得所有角色名稱（含團隊繼承）
     */
    public function getAllRoleNamesAttribute()
    {
        return $this->getRoleNames();
    }

    /**
     * 檢查使用者是否屬於某個團隊
     */
    public function isInTeam(string $teamName): bool
    {
        return $this->team && $this->team->name === $teamName;
    }

    /**
     * 檢查使用者是否屬於某個部門
     */
    public function isInDepartment(string $deptName): bool
    {
        return $this->department && $this->department->name === $deptName;
    }
	
	protected static function booted(): void
	{
		static::created(function (User $user) {
			$user->syncTeamDefaultRole();
		});

		static::updated(function (User $user) {
			if ($user->wasChanged('team_id')) {
				$user->syncTeamDefaultRole();
			}
		});
	}

	/**
	 * 同步團隊預設角色
	 */
	public function syncTeamDefaultRole(): void
	{
		if (! $this->team_id) {
			return;
		}

		$team = $this->team()->with('roles')->first();
		if (! $team) {
			return;
		}

		$defaultRoles = $team->roles()->pluck('name')->toArray();

		if (!empty($defaultRoles)) {
			$this->syncRoles($defaultRoles);
		}
	}
}
