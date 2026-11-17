// Client-side entry point.
//
// Alpine 3 ships with Livewire, so no separate Alpine import is needed.
// Bootstrap's JavaScript is intentionally not imported (ADR-0001).
import 'bootstrap-icons/font/bootstrap-icons.css';
import { initPageLifecycle } from './ui/page-lifecycle';

initPageLifecycle();
