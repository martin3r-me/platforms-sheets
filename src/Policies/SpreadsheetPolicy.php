<?php

namespace Platform\Sheets\Policies;

use Platform\Core\Policies\BasePolicy;
use Platform\Core\Models\User;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Enums\FolderRole;

class SpreadsheetPolicy extends BasePolicy
{
    public function view(User $user, $spreadsheet): bool
    {
        if ($this->isOwner($user, $spreadsheet)) {
            return true;
        }

        if (!$this->isInTeam($user, $spreadsheet)) {
            return false;
        }

        if ($spreadsheet->folder_id && $spreadsheet->folder) {
            $role = $spreadsheet->folder->getEffectiveRoleForUser($user->id);
            return $role !== null;
        }

        return true;
    }

    public function update(User $user, $spreadsheet): bool
    {
        if ($this->isOwner($user, $spreadsheet)) {
            return true;
        }

        if (!$this->isInTeam($user, $spreadsheet)) {
            return false;
        }

        if ($spreadsheet->folder_id && $spreadsheet->folder) {
            $role = $spreadsheet->folder->getEffectiveRoleForUser($user->id);
            return in_array($role, [
                FolderRole::OWNER->value,
                FolderRole::ADMIN->value,
                FolderRole::MEMBER->value,
            ], true);
        }

        return true;
    }

    public function delete(User $user, $spreadsheet): bool
    {
        if ($this->isOwner($user, $spreadsheet)) {
            return true;
        }

        if (!$this->isInTeam($user, $spreadsheet)) {
            return false;
        }

        if ($spreadsheet->folder_id && $spreadsheet->folder) {
            $role = $spreadsheet->folder->getEffectiveRoleForUser($user->id);
            return in_array($role, [
                FolderRole::OWNER->value,
                FolderRole::ADMIN->value,
            ], true);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    protected function getUserRole(User $user, $model): ?string
    {
        if ($model->folder_id && $model->folder) {
            return $model->folder->getEffectiveRoleForUser($user->id);
        }

        if ($this->isOwner($user, $model)) {
            return FolderRole::OWNER->value;
        }

        return null;
    }
}
