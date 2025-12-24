// src/composables/useCalendar.js
import { computed, onMounted, ref, watch, onUnmounted } from 'vue';
import { debounce } from 'lodash-es';
import apiClient from '../services/apiClient';
import calendarApi from '../services/calendarApi';
import equipmentApi from '../services/equipmentApi';
import clinicApi from '../services/clinicApi';
import assistantApi from '../services/assistantApi';
import { useAuth } from './useAuth';
import { useToast } from './useToast';

export function useCalendar() {
    const { user, initAuth } = useAuth();
    const { success: toastSuccess, error: toastError, info: toastInfo } = useToast();

    // UI state
    const viewMode = ref('timeGridWeek');
    const selectedDoctorId = ref('');
    const selectedDoctorIds = ref([]);
    const selectedProcedureId = ref('');
    const selectedEquipmentId = ref('');
    const selectedRoomId = ref('');
    const selectedRoomIds = ref([]);
    const selectedAssistantId = ref('');
    const selectedSpecializations = ref([]);
    const resourceViewType = ref('doctor');
    const isFollowUp = ref(false);
    const allowSoftConflicts = ref(false);

    // Data
    const doctors = ref([]);
    const procedures = ref([]);
    const rooms = ref([]);
    const equipments = ref([]);
    const assistants = ref([]);
    const clinics = ref([]);
    const selectedClinicId = ref('');

    const loading = ref(false);
    const loadingSlots = ref(false);
    const error = ref(null);

    // Booking modal
    const isBookingOpen = ref(false);
    const bookingLoading = ref(false);
    const bookingError = ref(null);
    const booking = ref({
        start: null,
        end: null,
        patient_id: '',
        comment: '',
        waitlist_entry_id: '',
    });

    // Calendar data
    const events = ref([]);
    const availabilityBgEvents = ref([]);
    const calendarBlocks = ref([]);
    const calendarRef = ref(null);

    // Drag context
    const dragContextActive = ref(false);

    // Active visible range (from datesSet)
    const activeRange = ref({
        start: null, // Date
        end: null,   // Date (exclusive)
        fromDate: null, // 'YYYY-MM-DD'
        toDate: null,   // 'YYYY-MM-DD'
    });

    // request guards (anti race / anti loop)
    let eventsReqId = 0;
    let slotsReqId = 0;
    let blocksReqId = 0;
    let datesSetKey = '';
    let datesSetInFlight = false;

    // Slots cache with TTL
    const slotsCache = new Map();
    const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

    const debouncedRefreshSlots = debounce(async () => {
        await refreshAvailabilityBackground();
    }, 300);

    const defaultClinicId = computed(() =>
        user.value?.clinic_id ||
        user.value?.doctor?.clinic_id ||
        user.value?.doctor?.clinic?.id ||
        user.value?.clinics?.[0]?.clinic_id ||
        '',
    );

    const clinicId = computed(() =>
        selectedClinicId.value ||
        defaultClinicId.value ||
        null,
    );

    const showClinicSelector = computed(() =>
        clinics.value.length > 1 || user.value?.global_role === 'super_admin',
    );

    // Utility functions
    const formatDateYMD = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };

    const formatTimeHM = (date) => {
        const h = String(date.getHours()).padStart(2, '0');
        const m = String(date.getMinutes()).padStart(2, '0');
        return `${h}:${m}`;
    };

    const minutesDiff = (a, b) => Math.max(0, Math.round((b.getTime() - a.getTime()) / 60000));

    const normalizeDateTimeForCalendar = (value) => {
        if (!value) return value;
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(value)) {
            return value.replace(' ', 'T');
        }
        return value;
    };

    // Cache management
    const buildSlotsKey = ({ doctorId, date, procedureId, roomId, equipmentId, assistantId, durationMinutes }) => [
        doctorId || '',
        date || '',
        procedureId || '',
        roomId || '',
        equipmentId || '',
        assistantId || '',
        durationMinutes || '',
    ].join('|');

    const clearExpiredCache = () => {
        const now = Date.now();
        for (const [key, value] of slotsCache.entries()) {
            if (now - value.timestamp > CACHE_TTL) slotsCache.delete(key);
        }
    };

    const fetchDoctorSlots = async ({ doctorId, date, procedureId, roomId, equipmentId, assistantId, durationMinutes }) => {
        clearExpiredCache();

        const key = buildSlotsKey({ doctorId, date, procedureId, roomId, equipmentId, assistantId, durationMinutes });
        const cached = slotsCache.get(key);
        if (cached && (Date.now() - cached.timestamp) < CACHE_TTL) return cached.data;

        const params = { date };
        if (procedureId) params.procedure_id = procedureId;
        if (roomId) params.room_id = roomId;
        if (equipmentId) params.equipment_id = equipmentId;
        if (assistantId) params.assistant_id = assistantId;

        const { data } = await calendarApi.getDoctorSlots(doctorId, params);
        const slots = Array.isArray(data?.slots) ? data.slots : [];
        const set = new Set(slots.map((s) => s.start));

        const result = { slots, set, raw: data };
        slotsCache.set(key, { data: result, timestamp: Date.now() });

        return result;
    };

    // Event helpers
    const buildEventTitle = (appt) => {
        const patient = appt?.patient?.full_name || 'Пацієнт';
        const proc = appt?.procedure?.name || '';
        return proc ? `${patient} • ${proc}` : patient;
    };

    const mapAppointmentsToEvents = (appts, { resourceType } = {}) => appts
        .map((appt) => {
            const resourceId = resourceType === 'doctor'
                ? appt?.doctor_id
                : resourceType === 'room'
                    ? appt?.room_id
                    : null;
            return {
                id: String(appt.id),
                title: buildEventTitle(appt),
                start: normalizeDateTimeForCalendar(appt.start_at),
                end: normalizeDateTimeForCalendar(appt.end_at),
                resourceId: resourceId ? String(resourceId) : undefined,
                extendedProps: { appointment: appt, status: appt.status },
                classNames: [`status-${appt.status || 'scheduled'}`],
            };
        })
        .filter((event) => event);

    const calendarBlockColors = {
        vacation: 'rgba(248, 113, 113, 0.25)',
        room_block: 'rgba(251, 146, 60, 0.25)',
        equipment_booking: 'rgba(192, 132, 252, 0.25)',
        personal_block: 'rgba(96, 165, 250, 0.25)',
    };

    const resolveCalendarBlockType = (block) =>
        block?.type || block?.block_type || block?.kind || 'block';

    const mapCalendarBlocksToEvents = (blocks, { resourceType } = {}) => blocks.map((block) => {
        const type = resolveCalendarBlockType(block);
        const start = normalizeDateTimeForCalendar(block.start_at || block.start || block.from);
        const end = normalizeDateTimeForCalendar(block.end_at || block.end || block.to);
        const resourceId = resourceType === 'doctor' ? block?.doctor_id : null;
        return {
            id: `calendar-block-${block.id || `${type}-${start}`}`,
            start,
            end,
            display: 'background',
            backgroundColor: calendarBlockColors[type] || 'rgba(148, 163, 184, 0.22)',
            classNames: ['calendar-block', `calendar-block-${type}`],
            resourceId: resourceId ? String(resourceId) : undefined,
            extendedProps: { block, type },
        };
    });

    // Data fetching
    const resolveDoctorClinicId = (doctor) =>
        doctor?.clinic_id ||
        doctor?.clinic?.id ||
        doctor?.clinic?.clinic_id ||
        doctor?.clinics?.[0]?.clinic_id ||
        null;

    const filteredDoctors = computed(() => {
        const base = clinicId.value
            ? doctors.value.filter((doctor) => Number(resolveDoctorClinicId(doctor)) === Number(clinicId.value))
            : doctors.value;

        if (!selectedSpecializations.value.length) return base;
        return base.filter((doctor) => selectedSpecializations.value.includes(doctor.specialization));
    });

    const specializations = computed(() => {
        const list = new Set();
        doctors.value.forEach((doctor) => {
            if (doctor?.specialization) list.add(doctor.specialization);
        });
        return Array.from(list).sort((a, b) => a.localeCompare(b));
    });

    const isResourceView = computed(() => viewMode.value.startsWith('resourceTimeGrid'));

    const syncSelectedDoctorWithClinic = () => {
        const list = filteredDoctors.value;
        if (!list.length) {
            selectedDoctorId.value = '';
            selectedDoctorIds.value = [];
            events.value = [];
            availabilityBgEvents.value = [];
            return;
        }

        const hasSelected = list.some((doctor) => Number(doctor.id) === Number(selectedDoctorId.value));
        if (!hasSelected) {
            selectedDoctorId.value = String(list[0].id);
        }

        const validIds = new Set(list.map((doctor) => String(doctor.id)));
        const synced = selectedDoctorIds.value.filter((id) => validIds.has(String(id)));
        if (!synced.length) {
            selectedDoctorIds.value = list.map((doctor) => String(doctor.id));
        } else {
            selectedDoctorIds.value = synced;
        }
    };

    const syncSelectedRooms = () => {
        const validIds = new Set(rooms.value.map((room) => String(room.id)));
        const synced = selectedRoomIds.value.filter((id) => validIds.has(String(id)));
        selectedRoomIds.value = synced.length ? synced : rooms.value.map((room) => String(room.id));
    };

    const fetchDoctors = async () => {
        const { data } = await apiClient.get('/doctors');
        doctors.value = Array.isArray(data) ? data : (data?.data || []);
    };

    const fetchProcedures = async () => {
        const { data } = await apiClient.get('/procedures');
        procedures.value = Array.isArray(data) ? data : (data?.data || []);
    };

    const fetchClinics = async () => {
        if (user.value?.global_role === 'super_admin') {
            const { data } = await clinicApi.list();
            clinics.value = data.data ?? data;
        } else {
            const { data } = await clinicApi.listMine();
            clinics.value = (data.clinics ?? []).map((clinic) => ({
                id: clinic.clinic_id,
                name: clinic.clinic_name,
            }));
        }

        if (!selectedClinicId.value) {
            selectedClinicId.value = defaultClinicId.value || clinics.value[0]?.id || '';
        }
    };

    const fetchRooms = async () => {
        if (!clinicId.value) {
            rooms.value = [];
            return;
        }
        const { data } = await apiClient.get('/rooms', { params: { clinic_id: clinicId.value } });
        rooms.value = Array.isArray(data) ? data : (data?.data || []);
        syncSelectedRooms();
    };

    const fetchEquipments = async () => {
        if (!clinicId.value) {
            equipments.value = [];
            return;
        }
        const { data } = await equipmentApi.list({ clinic_id: clinicId.value });
        equipments.value = Array.isArray(data) ? data : (data?.data || []);
    };

    const fetchAssistants = async () => {
        if (!clinicId.value) {
            assistants.value = [];
            return;
        }
        const { data } = await assistantApi.list({ clinic_id: clinicId.value });
        assistants.value = Array.isArray(data) ? data : (data?.data || []);
    };

    const loadAppointmentsRange = async (doctorId, fromDate, toDate) => {
        const { data } = await calendarApi.getAppointments({
            doctor_id: doctorId,
            from_date: fromDate,
            to_date: toDate,
            clinic_id: clinicId.value, // ✅
        });

        return Array.isArray(data) ? data : (data?.data || []);
    };

    const loadCalendarBlocksRange = async ({ doctorId, fromDate, toDate }) => {
        const { data } = await calendarApi.getCalendarBlocks({
            doctor_id: doctorId,
            clinic_id: clinicId.value,
            from_date: fromDate,
            from: fromDate,
            to: toDate,
        });

        return Array.isArray(data) ? data : (data?.data || []);
    };
    const ensureRange = () => {
        // якщо datesSet ще не прийшов — беремо з view
        if (activeRange.value?.fromDate && activeRange.value?.toDate) return activeRange.value;

        const api = calendarRef.value?.getApi?.();
        const view = api?.view;

        const start = view?.activeStart ? new Date(view.activeStart) : new Date();
        const end = view?.activeEnd ? new Date(view.activeEnd) : new Date(Date.now() + 7 * 86400000);

        const fromDate = formatDateYMD(start);
        const toDate = formatDateYMD(new Date(end.getTime() - 86400000));

        activeRange.value = { start, end, fromDate, toDate };
        return activeRange.value;
    };

    const resolveDoctorIdsForEvents = () => {
        if (isResourceView.value && resourceViewType.value === 'doctor') {
            return selectedDoctorIds.value.length ? selectedDoctorIds.value : [];
        }

        if (isResourceView.value && resourceViewType.value === 'room') {
            return selectedDoctorIds.value.length ? selectedDoctorIds.value : (selectedDoctorId.value ? [selectedDoctorId.value] : []);
        }

        return selectedDoctorId.value ? [selectedDoctorId.value] : [];
    };

    const loadEvents = async (range = null) => {
        const doctorIds = resolveDoctorIdsForEvents();
        if (!doctorIds.length) return;

        const reqId = ++eventsReqId;
        loading.value = true;
        error.value = null;

        try {
            const r = range ? range : ensureRange();
            const apptBatches = await Promise.all(
                doctorIds.map((doctorId) => loadAppointmentsRange(doctorId, r.fromDate, r.toDate)),
            );
            const appts = apptBatches.flat();

            // якщо під час запиту прийшов новіший — не застосовуємо
            if (reqId !== eventsReqId) return;

            let mapped = mapAppointmentsToEvents(appts, {
                resourceType: isResourceView.value ? resourceViewType.value : null,
            });

            if (isResourceView.value && resourceViewType.value === 'room' && selectedRoomIds.value.length) {
                const allowed = new Set(selectedRoomIds.value.map((id) => String(id)));
                mapped = mapped.filter((event) => event.resourceId && allowed.has(String(event.resourceId)));
            }

            events.value = mapped;
        } catch (e) {
            const message = e.response?.data?.message || e.message || 'Помилка завантаження подій';
            error.value = message;
            toastError(message);
        } finally {
            if (reqId === eventsReqId) loading.value = false;
        }
    };

    const loadCalendarBlocks = async (range = null) => {
        if (isResourceView.value && resourceViewType.value === 'room') {
            calendarBlocks.value = [];
            return;
        }

        const doctorIds = resolveDoctorIdsForEvents();
        if (!doctorIds.length) return;

        const reqId = ++blocksReqId;

        try {
            const r = range ? range : ensureRange();
            const blocksBatches = await Promise.all(
                doctorIds.map((doctorId) => loadCalendarBlocksRange({
                    doctorId,
                    fromDate: r.fromDate,
                    toDate: r.toDate,
                })),
            );
            const blocks = blocksBatches.flat();

            if (reqId !== blocksReqId) return;

            calendarBlocks.value = mapCalendarBlocksToEvents(blocks, {
                resourceType: isResourceView.value ? resourceViewType.value : null,
            });
        } catch (e) {
            console.warn('Помилка завантаження блоків календаря:', e);
        }
    };

    // Availability slots
    const mergeSlotsToIntervals = (slots) => {
        if (!slots.length) return [];
        const sorted = [...slots].sort((a, b) => a.start.localeCompare(b.start));
        const merged = [];
        for (const s of sorted) {
            const last = merged[merged.length - 1];
            if (!last) { merged.push({ start: s.start, end: s.end }); continue; }
            if (last.end === s.start) last.end = s.end;
            else merged.push({ start: s.start, end: s.end });
        }
        return merged;
    };

    const getDurationForContext = () => {
        if (selectedProcedureId.value) {
            const p = procedures.value.find((x) => x.id === Number(selectedProcedureId.value));
            return p?.duration_minutes || 30;
        }
        return 30;
    };

    const buildAvailabilityBgForRange = async ({
        doctorId,
        startDate,
        endDateExclusive,
        procedureId,
        roomId,
        equipmentId,
        assistantId,
        durationMinutes,
        resourceId,
    }) => {
        const api = calendarRef.value?.getApi?.();
        const view = api?.view;
        if (view?.type === 'dayGridMonth') return [];

        const cursor = new Date(startDate);
        const bg = [];

        while (cursor < endDateExclusive) {
            const date = formatDateYMD(cursor);
            try {
                const { slots } = await fetchDoctorSlots({ doctorId, date, procedureId, roomId, equipmentId, assistantId, durationMinutes });
                const intervals = mergeSlotsToIntervals(slots);

                for (const it of intervals) {
                    bg.push({
                        id: `free-${doctorId}-${date}-${it.start}`,
                        start: `${date}T${it.start}:00`,
                        end: `${date}T${it.end}:00`,
                        display: 'background',
                        overlap: true,
                        backgroundColor: 'rgba(16, 185, 129, 0.22)',
                        classNames: ['free-slot'],
                        resourceId: resourceId ? String(resourceId) : undefined,
                    });
                }
            } catch (err) {
                console.warn(`Помилка завантаження слотів для ${date}:`, err);
            }
            cursor.setDate(cursor.getDate() + 1);
        }

        return bg;
    };

    const refreshAvailabilityBackground = async (range = null) => {
        if (!calendarRef.value) return;

        const api = calendarRef.value.getApi();
        const view = api.view;
        if (!view) return;

        if (view.type === 'dayGridMonth') {
            availabilityBgEvents.value = [];
            return;
        }

        if (!resolveDoctorIdsForEvents().length) return;
        if (dragContextActive.value) return;

        const reqId = ++slotsReqId;
        loadingSlots.value = true;

        try {
            const r = range ? range : ensureRange();

            const duration = getDurationForContext();
            if (isResourceView.value && resourceViewType.value === 'room') {
                availabilityBgEvents.value = [];
                return;
            }

            const doctorIds = resolveDoctorIdsForEvents();
            const bgBatches = await Promise.all(
                doctorIds.map((doctorId) => buildAvailabilityBgForRange({
                    doctorId,
                    startDate: r.start,
                    endDateExclusive: r.end,
                    procedureId: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
                    roomId: selectedRoomId.value ? Number(selectedRoomId.value) : null,
                    equipmentId: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
                    assistantId: selectedAssistantId.value ? Number(selectedAssistantId.value) : null,
                    durationMinutes: duration,
                    resourceId: isResourceView.value ? doctorId : null,
                })),
            );
            const bg = bgBatches.flat();

            if (reqId !== slotsReqId) return;
            availabilityBgEvents.value = bg;
        } catch (err) {
            console.error('Помилка оновлення фонових слотів:', err);
        } finally {
            if (reqId === slotsReqId) loadingSlots.value = false;
        }
    };

    // Booking
    const resetBooking = () => {
        booking.value = { start: null, end: null, patient_id: '', comment: '', waitlist_entry_id: '' };
    };

    const openBooking = (info) => {
        booking.value.start = info.start;
        booking.value.end = info.end;
        booking.value.patient_id = '';
        booking.value.comment = '';
        booking.value.waitlist_entry_id = '';
        bookingError.value = null;
        isBookingOpen.value = true;
    };

    const closeBooking = () => {
        isBookingOpen.value = false;
        bookingError.value = null;
    };

    const createAppointment = async (payload) => {
        if (!selectedDoctorId.value || !payload?.start) {
            toastError('Вкажіть час початку');
            return;
        }

        bookingLoading.value = true;
        bookingError.value = null;

        try {
            const start = payload.start instanceof Date ? payload.start : new Date(payload.start);

            const apiPayload = {
                doctor_id: selectedDoctorId.value,
                date: formatDateYMD(start),
                time: formatTimeHM(start),
                patient_id: payload.patient_id ? Number(payload.patient_id) : null,
                procedure_id: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
                room_id: selectedRoomId.value ? Number(selectedRoomId.value) : null,
                equipment_id: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
                assistant_id: selectedAssistantId.value ? Number(selectedAssistantId.value) : null,
                is_follow_up: !!isFollowUp.value,
                allow_soft_conflicts: !!allowSoftConflicts.value,
                waitlist_entry_id: payload.waitlist_entry_id ? Number(payload.waitlist_entry_id) : null,
                comment: payload.comment || null,
                source: 'crm',
            };

            await calendarApi.createAppointment(apiPayload);
            toastSuccess('Запис успішно створено');
            isBookingOpen.value = false;
            resetBooking();

            await refreshCalendar();
        } catch (e) {
            const message = e.response?.data?.message || e.message || 'Помилка створення запису';
            bookingError.value = message;
            toastError(message);
        } finally {
            bookingLoading.value = false;
        }
    };

    // Drag & drop
    const showDragAvailability = async (event) => {
        const appt = event?.extendedProps?.appointment;
        if (!appt) return;

        const api = calendarRef.value?.getApi?.();
        const view = api?.view;
        if (!view) return;
        if (viewMode.value === 'dayGridMonth') return;

        dragContextActive.value = true;
        loadingSlots.value = true;

        try {
            const r = ensureRange();
            const procedureId = appt?.procedure_id ?? null;
            const roomId = appt?.room_id ?? null;
            const equipmentId = appt?.equipment_id ?? null;

            const startOld = appt?.start_at ? new Date(normalizeDateTimeForCalendar(appt.start_at)) : null;
            const endOld = appt?.end_at ? new Date(normalizeDateTimeForCalendar(appt.end_at)) : null;
            const duration = (startOld && endOld) ? minutesDiff(startOld, endOld) : 30;

            availabilityBgEvents.value = await buildAvailabilityBgForRange({
                doctorId: appt?.doctor_id || selectedDoctorId.value,
                startDate: r.start,
                endDateExclusive: r.end,
                procedureId,
                roomId,
                equipmentId,
                assistantId: appt?.assistant_id ?? (selectedAssistantId.value ? Number(selectedAssistantId.value) : null),
                durationMinutes: duration,
            });
        } catch (err) {
            console.error('Помилка завантаження drag availability:', err);
        } finally {
            loadingSlots.value = false;
        }
    };

    const hideDragAvailability = async () => {
        dragContextActive.value = false;
        await refreshAvailabilityBackground();
    };

    const handleEventMoveResize = async (info, kind) => {
        const id = info.event.id;
        const appt = info.event.extendedProps?.appointment;

        try {
            const start = info.event.start;
            if (!start) throw new Error('Не вдалося визначити час початку');

            const date = formatDateYMD(start);
            const time = formatTimeHM(start);

            const procedureId = appt?.procedure_id ?? null;
            const roomId = appt?.room_id ?? null;
            const equipmentId = appt?.equipment_id ?? null;

            const startOld = appt?.start_at ? new Date(normalizeDateTimeForCalendar(appt.start_at)) : null;
            const endOld = appt?.end_at ? new Date(normalizeDateTimeForCalendar(appt.end_at)) : null;
            const duration = (startOld && endOld) ? minutesDiff(startOld, endOld) : 30;

            const slotRes = await fetchDoctorSlots({
                doctorId: appt?.doctor_id || selectedDoctorId.value,
                date,
                procedureId,
                roomId,
                equipmentId,
                assistantId: appt?.assistant_id ?? (selectedAssistantId.value ? Number(selectedAssistantId.value) : null),
                durationMinutes: duration,
            });

            if (!slotRes.set.has(time)) {
                info.revert();
                toastInfo('Цей час недоступний. Переносьте запис тільки на підсвічений час.');
                return;
            }

            const payload = {
                doctor_id: appt?.doctor_id || selectedDoctorId.value,
                date,
                time,
                patient_id: appt?.patient_id ?? null,
                procedure_id: appt?.procedure_id ?? null,
                room_id: appt?.room_id ?? null,
                equipment_id: appt?.equipment_id ?? null,
                assistant_id: appt?.assistant_id ?? null,
                is_follow_up: !!appt?.is_follow_up,
                allow_soft_conflicts: !!allowSoftConflicts.value,
            };

            await calendarApi.updateAppointment(id, payload);
            toastSuccess(kind === 'resize' ? 'Запис змінено' : 'Запис перенесено');

            await refreshCalendar();
        } catch (e) {
            info.revert();
            const msg = e.response?.data?.message || e.message || `Помилка при ${kind}`;
            toastError(msg);
        } finally {
            await hideDragAvailability();
        }
    };

    const selectAllow = async (selectInfo) => {
        try {
            if (!selectedDoctorId.value) return false;

            const api = calendarRef.value?.getApi?.();
            const view = api?.view;
            if (view?.type === 'dayGridMonth') return true;

            const date = formatDateYMD(selectInfo.start);
            const time = formatTimeHM(selectInfo.start);
            const duration = minutesDiff(selectInfo.start, selectInfo.end) || 30;
            const resourceId = selectInfo?.resource?.id;
            const doctorId = resourceViewType.value === 'doctor' && resourceId
                ? resourceId
                : selectedDoctorId.value;
            const roomId = resourceViewType.value === 'room' && resourceId
                ? resourceId
                : selectedRoomId.value;

            const slotRes = await fetchDoctorSlots({
                doctorId,
                date,
                procedureId: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
                roomId: roomId ? Number(roomId) : null,
                equipmentId: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
                assistantId: selectedAssistantId.value ? Number(selectedAssistantId.value) : null,
                durationMinutes: duration,
            });

            return slotRes.set.has(time);
        } catch {
            return true;
        }
    };

    const handleSelect = (info) => {
        const resourceId = info?.resource?.id;
        if (resourceViewType.value === 'doctor' && resourceId) {
            selectedDoctorId.value = String(resourceId);
        }
        if (resourceViewType.value === 'room' && resourceId) {
            selectedRoomId.value = String(resourceId);
        }
        openBooking(info);
    };

    const handleEventClick = (info) => {
        const appt = info.event.extendedProps?.appointment;
        const patient = appt?.patient?.full_name || 'Пацієнт';
        const proc = appt?.procedure?.name || 'без процедури';
        const room = appt?.room?.name ? `, кабінет: ${appt.room.name}` : '';
        const eq = appt?.equipment?.name ? `, обладнання: ${appt.equipment.name}` : '';
        const asst = appt?.assistant?.full_name ? `, асистент: ${appt.assistant.full_name}` : '';
        // eslint-disable-next-line no-alert
        alert(`${patient}\n${proc}${room}${eq}${asst}\nСтатус: ${appt?.status || info.event.extendedProps?.status}`);
    };

    // ✅ ВАЖЛИВО: тепер приймає info від FullCalendar
    const handleDatesSet = async (info) => {
        // 1) оновлюємо activeRange
        if (info?.start && info?.end) {
            const start = new Date(info.start);
            const end = new Date(info.end);

            const fromDate = formatDateYMD(start);
            const toDate = formatDateYMD(new Date(end.getTime() - 86400000)); // end exclusive → мінус 1 день

            activeRange.value = { start, end, fromDate, toDate };
        } else {
            ensureRange();
        }

        // 2) будуємо ключ діапазону + контекст (щоб не фетчити одне й те саме без кінця)
        const viewType = info?.view?.type || viewMode.value;
        const r = activeRange.value;

        const key = [
            viewType,
            r?.fromDate,
            r?.toDate,
            clinicId.value,
            resourceViewType.value,
            selectedDoctorId.value,
            (selectedDoctorIds.value || []).join(','),
            (selectedRoomIds.value || []).join(','),
            selectedProcedureId.value,
            selectedRoomId.value,
            selectedEquipmentId.value,
            selectedAssistantId.value,
        ].join('|');

        // 3) анти-петля: якщо це той самий ключ — нічого не робимо
        if (key === datesSetKey) return;
        if (datesSetInFlight) return;

        datesSetKey = key;
        datesSetInFlight = true;

        try {
            // якщо нема клініки або нема лікарів для показу — не запускаємо фетч
            const doctorIds = resolveDoctorIdsForEvents();
            if (!clinicId.value || !doctorIds.length) return;

            // 4) грузимо все разом (менше шансів на “дьорг” календаря)
            await Promise.all([
                loadEvents(r),
                loadCalendarBlocks(r),
                refreshAvailabilityBackground(r),
            ]);
        } finally {
            datesSetInFlight = false;
        }
    };

    const refreshCalendar = async () => {
        const r = ensureRange();
        await Promise.all([loadEvents(r), loadCalendarBlocks(r), refreshAvailabilityBackground(r)]);
    };

    const initialize = async () => {
        try {
            loading.value = true;
            await initAuth();
            await Promise.all([fetchDoctors(), fetchProcedures(), fetchClinics()]);
            syncSelectedDoctorWithClinic();
            await Promise.all([fetchRooms(), fetchEquipments(), fetchAssistants()]);
            await refreshCalendar();
        } catch (initError) {
            console.error('Помилка ініціалізації:', initError);
            const message = initError?.response?.data?.message || initError?.message || 'Помилка ініціалізації';
            error.value = message;
            toastError(message);
        } finally {
            loading.value = false;
        }
    };

    const cleanup = () => {
        slotsCache.clear();
        if (debouncedRefreshSlots?.cancel) debouncedRefreshSlots.cancel();
    };

    // Watchers
    watch([selectedProcedureId, selectedRoomId, selectedEquipmentId, selectedAssistantId], () => {
        debouncedRefreshSlots();
    });

    watch(selectedClinicId, () => {
        selectedRoomId.value = '';
        selectedEquipmentId.value = '';
        selectedAssistantId.value = '';
    });

    watch(selectedSpecializations, () => {
        syncSelectedDoctorWithClinic();
    });

    // ✅ РОЗДІЛЕНО:
    watch(viewMode, async () => {
        const api = calendarRef.value?.getApi?.();
        if (api) api.changeView(viewMode.value);
        // datesSet прийде автоматом після changeView і сам підтягне дані
    });

    watch(selectedDoctorId, async () => {
        if (selectedDoctorId.value && !selectedDoctorIds.value.includes(String(selectedDoctorId.value))) {
            selectedDoctorIds.value = [...new Set([...selectedDoctorIds.value, String(selectedDoctorId.value)])];
        }
        await refreshCalendar();
    });

    watch(selectedDoctorIds, async () => {
        if (isResourceView.value && resourceViewType.value === 'doctor') {
            if (!selectedDoctorIds.value.length) {
                syncSelectedDoctorWithClinic();
            }
            await refreshCalendar();
        }
    });

    watch(selectedRoomIds, async () => {
        if (isResourceView.value && resourceViewType.value === 'room') {
            await refreshCalendar();
        }
    });

    watch(resourceViewType, async () => {
        if (resourceViewType.value === 'room') {
            syncSelectedRooms();
        } else {
            syncSelectedDoctorWithClinic();
        }
        await refreshCalendar();
    });

    watch(clinicId, async () => {
        syncSelectedDoctorWithClinic();
        await Promise.all([fetchRooms(), fetchEquipments(), fetchAssistants()]);
        await refreshAvailabilityBackground();
    });

    watch(selectedProcedureId, () => {
        const p = procedures.value.find((x) => x.id === Number(selectedProcedureId.value));
        if (p?.equipment_id) selectedEquipmentId.value = p.equipment_id;
        if (p?.default_room_id) selectedRoomId.value = p.default_room_id;
        if (!p?.requires_assistant) selectedAssistantId.value = '';
    });

    onUnmounted(() => cleanup());

    onMounted(async () => {
        await initialize();
    });

    return {
        calendarRef,
        events,
        availabilityBgEvents,
        calendarBlocks,

        viewMode,
        selectedDoctorId,
        selectedDoctorIds,
        selectedProcedureId,
        selectedEquipmentId,
        selectedRoomId,
        selectedRoomIds,
        selectedAssistantId,
        selectedSpecializations,
        resourceViewType,
        specializations,
        isResourceView,
        isFollowUp,
        allowSoftConflicts,

        doctors,
        filteredDoctors,
        procedures,
        rooms,
        equipments,
        assistants,
        clinics,
        selectedClinicId,
        showClinicSelector,

        loading,
        loadingSlots,
        error,

        booking,
        isBookingOpen,
        bookingLoading,
        bookingError,

        initialize,
        refreshCalendar,
        loadEvents,
        loadCalendarBlocks,
        refreshAvailabilityBackground,

        createAppointment,
        closeBooking,
        openBooking,

        handleSelect,
        handleEventClick,
        handleEventMoveResize,
        showDragAvailability,
        hideDragAvailability,
        selectAllow,

        handleDatesSet,
        cleanup,
    };
}
