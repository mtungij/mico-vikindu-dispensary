<?php

namespace Tests\Feature;

use Tests\TestCase;

class ComponentRenderTest extends TestCase
{
    public function test_modal_component_renders_title_and_slots(): void
    {
        $this->blade('<x-modal :show="true" title="Kichwa cha Modal">Maudhui ya Modal</x-modal>')
            ->assertSee('Kichwa cha Modal')
            ->assertSee('Maudhui ya Modal');
    }
}
