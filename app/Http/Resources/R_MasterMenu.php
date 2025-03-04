<?php

namespace App\Http\Resources;

use App\Models\M_MasterMenu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_MasterMenu extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'menu_name' => $this->menu_name,
            'route' => $this->route,
            'parent' => $this->check_menu_id_existence($request, $this->id),
            'order' => $this->order,
            'leading' => $this->leading,
            'action' => $this->action,
            'status' => $this->status,
            'ability' => $this->ability,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at,
            'deleted_by' => $this->deleted_by,
            'deleted_at' => $this->deleted_at
        ];

        return $data;
    }

    function check_menu_id_existence($request)
    {
        $response = $request->getUri();

        if ($response !== false) {
            if (strpos($response, "/{$this->id}") !== false) {
                return M_MasterMenu::getParentMenuName($this->parent);
            }
        }

        return M_MasterMenu::getParentMenuName($this->parent, false);
    }
}
