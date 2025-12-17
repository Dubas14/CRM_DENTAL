# Calendar backend–frontend sync audit

## Migrations vs models
- `appointments.procedure_id` → Present in `$fillable` and `procedure()` relation in `Appointment` (OK).
- `appointments.room_id` → Present in `$fillable` and `room()` relation in `Appointment` (OK).
- `appointments.equipment_id` → Present in `$fillable` and `equipment()` relation in `Appointment` (OK).
- `appointments.assistant_id` → Present in `$fillable`, but no relation eager-loaded by controllers; no resource exposes it (needs API exposure).
- `appointments.is_follow_up` → In `$fillable`/casts but not surfaced by controllers/resources (needs API exposure).
- `procedures.equipment_id` → Added in migration and `$fillable`, with `equipment()` relation (OK).
- `procedures.default_room_id` → `$fillable` and `defaultRoom()` relation (OK).
- `rooms.clinic_id` → `$fillable` and `clinic()` relation (OK).
- `waitlist_entries.clinic_id/patient_id/doctor_id/procedure_id` → In `$fillable` with `clinic()`, `patient()`, `doctor()`, `procedure()` relations (OK).
- `schedule_exceptions.doctor_id` → In `$fillable` with `doctor()` relation (OK).

## API responses vs expected calendar fields
- `AppointmentController@store` returns raw `Appointment` without relations; payload includes `procedure_id`, `room_id`, `equipment_id`, `assistant_id`, `is_follow_up`, but response lacks nested doctor/patient/procedure/room/equipment/assistant info.
- `AppointmentController@update` responds with `appointment->fresh(['patient','doctor','procedure','room'])`; **equipment**, **assistant**, **clinic**, **is_follow_up** are not eagerly loaded/serialized (missing in response JSON).
- `AppointmentController@doctorAppointments`/index path (used by calendar) returns appointments without explicit eager loads or resources, so nested procedure/room/equipment/assistant details are absent unless implicit; no `is_follow_up` exposure.
- `DoctorScheduleController@slots/recommended` accepts `procedure_id` and `equipment_id` but not `room_id` or `assistant_id`; slots responses do not return equipment/room requirements.
- `WaitlistController@index/store` returns entries with `patient`, `doctor`, `procedure`; equipment/room/assistant/follow-up context absent (OK given schema).
- `RoomController`/`ProcedureController` return plain models; procedures include `equipment_id`/`requires_room`/`requires_assistant` but responses omit nested room/equipment objects unless `show/update` (only `defaultRoom`).

## Frontend usage vs API fields
- `calendarApi` methods mirror backend endpoints but create/update appointment payloads only send `doctor_id`, `date`, `time`, `patient_id`, `procedure_id`, `equipment_id`, `comment`; **room_id**, **assistant_id**, **is_follow_up**, **waitlist_entry_id**, **allow_soft_conflicts**, and status updates are not forwarded (payload gap).
- `CalendarModule.vue` uses `procedure_id` and `equipment_id` when booking; does not handle `room_id`, `assistant_id`, `is_follow_up`, or display of related entities in appointments list (UI gap).
- `CalendarSlotPicker.vue` filters slots by `procedure_id`/`equipment_id` only; no awareness of `room_id`/assistant/follow-up flags.
- Waitlist components (`WaitlistRequestForm.vue`, `WaitlistCandidatesPanel.vue`) only handle doctor/procedure/patient fields; no equipment/room/assistant/follow-up support (matches API).
- `AppointmentModal.vue` renders appointment status/patient fields only; no procedure/room/equipment/assistant/follow-up display.

## Key desynchronizations and recommendations
1. **Expose assistant/equipment/follow-up in Appointment API**
   - Add `assistant` relation loading and include `assistant_id`, `equipment_id`, `is_follow_up` in responses (e.g., `AppointmentController` responses or a dedicated `AppointmentResource`).
   - Eager load `equipment` alongside `patient`, `doctor`, `procedure`, `room` in update/show endpoints; mirror in doctor appointments listing.
2. **Pass new fields from frontend when booking/editing**
   - Extend `calendarApi.createAppointment/updateAppointment` payloads to send `room_id`, `assistant_id`, `is_follow_up`, `waitlist_entry_id`, `allow_soft_conflicts`, and status when applicable.
   - Update `CalendarModule.vue` form state to capture assistant/room/follow-up selections and propagate to booking calls; show selected room/equipment/assistant in appointment cards.
3. **Surface equipment/assistant requirements in slot selection**
   - Include `room_id` (and possibly assistant availability) in slot queries if backend supports it; display required equipment/assistant indicators in `CalendarSlotPicker` and appointment list.
4. **Optional: enrich procedure/room responses**
   - Consider returning nested `equipment` in `ProcedureController` responses to avoid extra lookups in UI when setting default equipment per procedure.

