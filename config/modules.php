<?php

/**
 * Canonical list of all 14 permission-gated CRM modules in exact sidebar order.
 * Keys are the slugs stored in each user's `permissions` column; labels are
 * shown in the Staff Access permission panel and drive sidebar visibility.
 */
return \App\Models\User::MODULES;
