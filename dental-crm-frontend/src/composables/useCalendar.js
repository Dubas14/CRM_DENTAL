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
    const NO_ROOM_RESOURCE_ID = 'no-room';
    const isFollowUp = ref(false);
    const allowSoftConflicts = ref(false);
    const diagnosticsEnabled = ref(true);
    const doctorSelectionMessage = ref('');

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
    const missingRoomAppointmentsCount = ref(0);

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

    // Для QCalendarScheduler
    const qcalendarView = ref(null);

    // Active visible range
    const activeRange = ref({
        start: null,
        end: null,
        fromDate: null,
        toDate: null,
    });

    // Request guards
    let eventsReqId = 0;
    let slotsReqId = 0;
    let blocksReqId = 0;
    let datesSetInFlight = false;

    // Slots cache with TTL
    const slotsCache = new Map();
    const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

    const debouncedRefreshSlots = debounce(async () => {
        await refreshAvailabilityBackground();
    }, 300);

    // Utility functions
    const formatDateYMD = (date) => {
        const d = new Date(date);
        if (isNaN(d.getTime())) return '';
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const d_ = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${d_}`;
    };

    const formatTimeHM = (date) => {
        const d = new Date(date);
        if (isNaN(d.getTime())) return '00:00';
        const h = String(d.getHours()).padStart(2, '0');
        const m = String(d.getMinutes()).padStart(2, '0');
        return `${h}:${m}`;
    };

    const minutesDiff = (a, b) => Math.max(0, Math.round((b.getTime() - a.getTime()) / 60000));

    // Для QCalendar формат: "YYYY-MM-DD HH:mm"
    const toQCalendarDateTime = (value) => {
        if (!value) return value;

        try {
            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                const dateStr = formatDateYMD(date);
                const timeStr = date.toTimeString().slice(0, 5);
                return `${dateStr} ${timeStr}`;
            }

            if (typeof value === 'string') {
                if (value.includes('T')) {
                    return value.replace('T', ' ').replace('Z', '').slice(0, 16);
                }
                return value;
            }
        } catch (e) {
            console.warn('Помилка конвертації дати:', value, e);
        }

        return value;
    };

    const fromQCalendarDateTime = (value) => {
        if (!value) return null;
        if (typeof value === 'string' && value.includes(' ')) {
            return new Date(value.replace(' ', 'T') + ':00');
        }
        return new Date(value);
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

    const mapAppointmentsToEvents = (appts, { resourceType, noRoomResourceId } = {}) => {
        return appts
            .filter((appt) => (appt?.start_at || appt?.start) && (appt?.end_at || appt?.end))
            .map((appt) => {
                const startAt = appt?.start_at || appt?.start;
                const endAt = appt?.end_at || appt?.end;
                const roomId = appt?.room_id;
                const resourceId = resourceType === 'doctor'
                    ? appt?.doctor_id
                    : resourceType === 'room'
                        ? (roomId != null ? roomId : (noRoomResourceId || null))
                        : null;

                return {
                    id: String(appt.id),
                    title: buildEventTitle(appt),
                    start: toQCalendarDateTime(startAt),
                    end: toQCalendarDateTime(endAt),
                    resourceId: resourceId ? String(resourceId) : undefined,
                    extendedProps: {
                        appointment: appt,
                        status: appt.status || 'scheduled'
                    },
                    classNames: [`status-${appt.status || 'scheduled'}`],
                    backgroundColor: getEventColorByStatus(appt.status),
                };
            });
    };

    const getEventColorByStatus = (status) => {
        const colors = {
            scheduled: '#3B82F6',
            confirmed: '#10B981',
            cancelled: '#EF4444',
            completed: '#6B7280',
            no_show: '#F59E0B',
        };
        return colors[status] || '#3B82F6';
    };

    const calendarBlockColors = {
        vacation: 'rgba(248, 113, 113, 0.25)',
        room_block: 'rgba(251, 146, 60, 0.25)',
        equipment_booking: 'rgba(192, 132, 252, 0.25)',
        personal_block: 'rgba(96, 165, 250, 0.25)',
        break: 'rgba(148, 163, 184, 0.25)',
    };

    const resolveCalendarBlockType = (block) =>
        block?.type || block?.block_type || block?.kind || 'block';

    const mapCalendarBlocksToEvents = (blocks, { resourceType } = {}) => {
        return blocks
            .filter(block => block?.start_at || block?.start)
            .map((block) => {
                const type = resolveCalendarBlockType(block);
                const start = toQCalendarDateTime(block.start_at || block.start);
                const end = toQCalendarDateTime(block.end_at || block.end);
                const resourceId = resourceType === 'doctor' ? block?.doctor_id : null;

                return {
                    id: `calendar-block-${block.id || `${type}-${start}`}`,
                    title: block.title || block.reason || type,
                    start,
                    end,
                    display: 'background',
                    backgroundColor: calendarBlockColors[type] || 'rgba(148, 163, 184, 0.22)',
                    classNames: ['calendar-block', `calendar-block-${type}`],
                    resourceId: resourceId ? String(resourceId) : undefined,
                    extendedProps: { block, type },
                };
            });
    };

    // ========== ВАЖЛИВО: Додаємо computed для baseView та uiMode ==========
    const viewModeToBaseView = (mode) => {
        if (mode === 'dayGridMonth') return 'month';
        if (String(mode).endsWith('Day')) return 'day';
        return 'week';
    };

    const viewModeToCalendarView = (mode) => viewModeToBaseView(mode);

    const resolveViewMode = (base, multi) => {
        if (base === 'month') return 'dayGridMonth';
        if (base === 'day') return multi ? 'resourceTimeGridDay' : 'timeGridDay';
        return multi ? 'resourceTimeGridWeek' : 'timeGridWeek';
    };

    const baseView = computed({
        get() {
            return viewModeToBaseView(viewMode.value);
        },
        set(v) {
            const multi = uiMode.value === 'multi';

            if (v === 'month' && multi) {
                viewMode.value = resolveViewMode('week', true);
                return;
            }

            viewMode.value = resolveViewMode(v, multi);
        },
    });

    const uiMode = computed({
        get() {
            return isResourceView.value ? 'multi' : 'single';
        },
        set(v) {
            const multi = v === 'multi';
            const currentBase = viewModeToBaseView(viewMode.value);

            if (currentBase === 'month' && multi) {
                viewMode.value = resolveViewMode('week', true);
                return;
            }

            viewMode.value = resolveViewMode(currentBase, multi);
        },
    });

    // Computed properties
    const defaultClinicId = computed(() =>
        user.value?.clinic_id ||
        user.value?.doctor?.clinic_id ||
        user.value?.doctor?.clinic?.id ||
        user.value?.clinics?.[0]?.clinic_id ||
        ''
    );

    const clinicId = computed(() =>
        selectedClinicId.value ||
        defaultClinicId.value ||
        null
    );

    const showClinicSelector = computed(() =>
        clinics.value.length > 1 || user.value?.global_role === 'super_admin'
    );

    const isResourceView = computed(() => viewMode.value.startsWith('resourceTimeGrid'));
    const calendarView = computed(() => viewModeToCalendarView(viewMode.value));

    const resolveDoctorClinicId = (doctor) =>
        doctor?.clinic_id ||
        doctor?.clinic?.id ||
        doctor?.clinic?.clinic_id ||
        doctor?.clinics?.[0]?.clinic_id ||
        null;

    const filteredDoctors = computed(() => {
        const base = clinicId.value
            ? doctors.value.filter((doctor) => {
                const doctorClinicId = resolveDoctorClinicId(doctor);
                return doctorClinicId && Number(doctorClinicId) === Number(clinicId.value);
            })
            : doctors.value;

        if (!selectedSpecializations.value.length) return base;
        return base.filter((doctor) => selectedSpecializations.value.includes(doctor.specialization));
    });

    const diagnosticsSnapshot = computed(() => ({
        selectedClinicId: selectedClinicId.value,
        clinicId: clinicId.value,
        selectedDoctorId: selectedDoctorId.value,
        selectedDoctorIds: [...selectedDoctorIds.value],
        filteredDoctorsCount: filteredDoctors.value.length,
        doctorsCount: doctors.value.length,
        clinicsCount: clinics.value.length,
    }));

    const logDiagnostics = (label) => {
        if (!diagnosticsEnabled.value) return;
        console.info('[Calendar diagnostics]', label, diagnosticsSnapshot.value);
    };

    const updateDoctorSelectionMessage = (context = 'unknown') => {
        if (!doctors.value.length) {
            doctorSelectionMessage.value = 'Лікарів не знайдено.';
        } else if (clinicId.value && !filteredDoctors.value.length) {
            doctorSelectionMessage.value = 'Для вибраної клініки немає лікарів.';
        } else {
            doctorSelectionMessage.value = '';
        }

        logDiagnostics(`doctor-selection:${context}`);
    };

    const specializations = computed(() => {
        const list = new Set();
        doctors.value.forEach((doctor) => {
            if (doctor?.specialization) list.add(doctor.specialization);
        });
        return Array.from(list).sort((a, b) => a.localeCompare(b));
    });

    const eventResourceIds = computed(() => {
        const ids = new Set();
        events.value.forEach((event) => {
            if (event?.resourceId) ids.add(String(event.resourceId));
        });
        return Array.from(ids);
    });

    const selectedDoctorResources = computed(() => {
        const ids = eventResourceIds.value.length
            ? new Set(eventResourceIds.value)
            : new Set(selectedDoctorIds.value.map(id => String(id)));
        return doctors.value.filter((doctor) => ids.has(String(doctor.id)));
    });

    const selectedRoomResources = computed(() => {
        const ids = eventResourceIds.value.length
            ? new Set(eventResourceIds.value)
            : new Set(selectedRoomIds.value.map(id => String(id)));
        return rooms.value.filter((room) => ids.has(String(room.id)));
    });

    // Data fetching functions
    const syncSelectedDoctorWithClinic = () => {
        const list = filteredDoctors.value;
        if (!list.length) {
            selectedDoctorId.value = '';
            selectedDoctorIds.value = [];
            events.value = [];
            availabilityBgEvents.value = [];
            updateDoctorSelectionMessage('sync-empty');
            return;
        }

        const hasSelected = list.some((doctor) => Number(doctor.id) === Number(selectedDoctorId.value));
        if (!hasSelected) {
            selectedDoctorId.value = String(list[0].id);
        }

        const validIds = new Set(list.map((doctor) => String(doctor.id)));
        const synced = selectedDoctorIds.value.filter((id) => validIds.has(String(id)));
        if (!synced.length && list.length) {
            selectedDoctorIds.value = [String(list[0].id)];
        } else {
            selectedDoctorIds.value = synced;
        }

        updateDoctorSelectionMessage('sync');
    };

    const syncSelectedRooms = () => {
        if (!rooms.value.length) {
            selectedRoomIds.value = [];
            return;
        }

        const validIds = new Set(rooms.value.map((room) => String(room.id)));
        const synced = selectedRoomIds.value.filter((id) => validIds.has(String(id)));
        if (!synced.length && rooms.value.length) {
            selectedRoomIds.value = [String(rooms.value[0].id)];
        } else {
            selectedRoomIds.value = synced;
        }
    };

    const fetchDoctors = async () => {
        try {
            const { data } = await apiClient.get('/doctors');
            doctors.value = Array.isArray(data) ? data : (data?.data || []);
            syncSelectedDoctorWithClinic();
            logDiagnostics('fetchDoctors');
        } catch (error) {
            console.error('Помилка завантаження лікарів:', error);
            doctors.value = [];
            updateDoctorSelectionMessage('fetchDoctors-error');
        }
    };

    const fetchProcedures = async () => {
        try {
            const params = clinicId.value ? { clinic_id: clinicId.value } : undefined;
            const { data } = await apiClient.get('/procedures', { params });
            const list = Array.isArray(data) ? data : (data?.data || []);

            const uniqueProcedures = [];
            const seenIds = new Set();
            const seenNames = new Set();

            list.forEach(proc => {
                if (proc.id && !seenIds.has(proc.id)) {
                    seenIds.add(proc.id);
                    uniqueProcedures.push(proc);
                } else if (proc.name && !seenNames.has(proc.name.toLowerCase())) {
                    seenNames.add(proc.name.toLowerCase());
                    uniqueProcedures.push(proc);
                }
            });

            procedures.value = uniqueProcedures;
        } catch (error) {
            console.error('Помилка завантаження процедур:', error);
            procedures.value = [];
        }
    };

    const fetchClinics = async () => {
        try {
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

            if (!selectedClinicId.value && clinics.value.length) {
                selectedClinicId.value = defaultClinicId.value || clinics.value[0]?.id || '';
            }

            syncSelectedDoctorWithClinic();
            logDiagnostics('fetchClinics');
        } catch (error) {
            console.error('Помилка завантаження клінік:', error);
            clinics.value = [];
        }
    };

    const fetchRooms = async () => {
        try {
            if (!clinicId.value) {
                rooms.value = [];
                return;
            }
            const { data } = await apiClient.get('/rooms', { params: { clinic_id: clinicId.value } });
            rooms.value = Array.isArray(data) ? data : (data?.data || []);
            syncSelectedRooms();
        } catch (error) {
            console.error('Помилка завантаження кабінетів:', error);
            rooms.value = [];
        }
    };

    const fetchEquipments = async () => {
        try {
            if (!clinicId.value) {
                equipments.value = [];
                return;
            }
            const { data } = await equipmentApi.list({ clinic_id: clinicId.value });
            equipments.value = Array.isArray(data) ? data : (data?.data || []);
        } catch (error) {
            console.error('Помилка завантаження обладнання:', error);
            equipments.value = [];
        }
    };

    const fetchAssistants = async () => {
        try {
            if (!clinicId.value) {
                assistants.value = [];
                return;
            }
            const { data } = await assistantApi.list({ clinic_id: clinicId.value });
            assistants.value = Array.isArray(data) ? data : (data?.data || []);
        } catch (error) {
            console.error('Помилка завантаження асистентів:', error);
            assistants.value = [];
        }
    };

    const loadAppointmentsRange = async (doctorId, fromDate, toDate) => {
        try {
            const { data } = await calendarApi.getAppointments({
                doctor_id: doctorId,
                from_date: fromDate,
                to_date: toDate,
                clinic_id: clinicId.value,
            });

            return Array.isArray(data) ? data : (data?.data || []);
        } catch (error) {
            console.error(`Помилка завантаження записів для лікаря ${doctorId}:`, error);
            return [];
        }
    };

    const loadCalendarBlocksRange = async ({ doctorId, fromDate, toDate }) => {
        try {
            if (!clinicId.value) {
                return [];
            }
            const { data } = await calendarApi.getCalendarBlocks({
                doctor_id: doctorId,
                clinic_id: clinicId.value,
                from_date: fromDate,
                to_date: toDate,
            });

            return Array.isArray(data) ? data : (data?.data || []);
        } catch (error) {
            console.error(`Помилка завантаження блоків для лікаря ${doctorId}:`, error);
            return [];
        }
    };

    const ensureRange = () => {
        if (activeRange.value?.start && activeRange.value?.end) {
            return activeRange.value;
        }

        const start = new Date();
        start.setHours(0, 0, 0, 0);

        const day = start.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        start.setDate(start.getDate() + diff);

        const end = new Date(start);
        end.setDate(end.getDate() + 7);

        const fromDate = formatDateYMD(start);
        const toDate = formatDateYMD(new Date(end.getTime() - 86400000));

        activeRange.value = { start, end, fromDate, toDate };
        return activeRange.value;
    };

    const resolveDoctorIdsForEvents = () => {
        if (isResourceView.value && resourceViewType.value === 'doctor') {
            return selectedDoctorIds.value.length
                ? selectedDoctorIds.value
                : (selectedDoctorId.value ? [selectedDoctorId.value] : []);
        }

        if (isResourceView.value && resourceViewType.value === 'room') {
            return selectedDoctorIds.value.length ? selectedDoctorIds.value : (selectedDoctorId.value ? [selectedDoctorId.value] : []);
        }

        return selectedDoctorId.value ? [selectedDoctorId.value] : [];
    };

    const loadEvents = async (range = null) => {
        const doctorIds = resolveDoctorIdsForEvents();
        if (!doctorIds.length) {
            events.value = [];
            return;
        }

        const reqId = ++eventsReqId;
        loading.value = true;
        error.value = null;

        try {
            const r = range ? range : ensureRange();
            const apptBatches = await Promise.all(
                doctorIds.map((doctorId) => loadAppointmentsRange(doctorId, r.fromDate, r.toDate))
            );
            const appts = apptBatches.flat();

            if (reqId !== eventsReqId) return;

            if (isResourceView.value && resourceViewType.value === 'room') {
                missingRoomAppointmentsCount.value = appts.filter(
                    (appt) => (appt?.start_at || appt?.start)
                        && (appt?.end_at || appt?.end)
                        && appt?.room_id == null
                ).length;
            } else {
                missingRoomAppointmentsCount.value = 0;
            }

            let mapped = mapAppointmentsToEvents(appts, {
                resourceType: isResourceView.value ? resourceViewType.value : null,
                noRoomResourceId: NO_ROOM_RESOURCE_ID,
            });

            if (isResourceView.value && resourceViewType.value === 'room' && selectedRoomIds.value.length) {
                const allowed = new Set(selectedRoomIds.value.map((id) => String(id)));
                mapped = mapped.filter((event) => event.resourceId === NO_ROOM_RESOURCE_ID
                    || (event.resourceId && allowed.has(String(event.resourceId))));
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
        if (!doctorIds.length) {
            calendarBlocks.value = [];
            return;
        }

        if (!clinicId.value) {
            calendarBlocks.value = [];
            return;
        }

        const reqId = ++blocksReqId;

        try {
            const r = range ? range : ensureRange();
            const blocksBatches = await Promise.all(
                doctorIds.map((doctorId) => loadCalendarBlocksRange({
                    doctorId,
                    fromDate: r.fromDate,
                    toDate: r.toDate,
                }))
            );
            const blocks = blocksBatches.flat();

            if (reqId !== blocksReqId) return;

            calendarBlocks.value = mapCalendarBlocksToEvents(blocks, {
                resourceType: isResourceView.value ? resourceViewType.value : null,
            });
        } catch (e) {
            console.warn('Помилка завантаження блоків календаря:', e);
            calendarBlocks.value = [];
        }
    };

    // Availability slots
    const mergeSlotsToIntervals = (slots) => {
        if (!slots?.length) return [];
        const sorted = [...slots].sort((a, b) => a.start.localeCompare(b.start));
        const merged = [];

        for (const s of sorted) {
            const last = merged[merged.length - 1];
            if (!last) {
                merged.push({ start: s.start, end: s.end });
                continue;
            }
            if (last.end === s.start) {
                last.end = s.end;
            } else {
                merged.push({ start: s.start, end: s.end });
            }
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
        const cursor = new Date(startDate);
        const bg = [];

        while (cursor < endDateExclusive) {
            const date = formatDateYMD(cursor);
            try {
                const { slots } = await fetchDoctorSlots({
                    doctorId,
                    date,
                    procedureId,
                    roomId,
                    equipmentId,
                    assistantId,
                    durationMinutes,
                });

                const intervals = mergeSlotsToIntervals(slots);

                for (const it of intervals) {
                    bg.push({
                        id: `free-${doctorId}-${date}-${it.start}`,
                        title: 'Вільний слот',
                        start: toQCalendarDateTime(`${date}T${it.start}:00`),
                        end: toQCalendarDateTime(`${date}T${it.end}:00`),
                        display: 'background',
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
        if (viewMode.value === 'dayGridMonth') {
            availabilityBgEvents.value = [];
            return;
        }

        if (!resolveDoctorIdsForEvents().length) {
            availabilityBgEvents.value = [];
            return;
        }

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
                doctorIds.map((doctorId) =>
                    buildAvailabilityBgForRange({
                        doctorId,
                        startDate: r.start,
                        endDateExclusive: r.end,
                        procedureId: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
                        roomId: selectedRoomId.value ? Number(selectedRoomId.value) : null,
                        equipmentId: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
                        assistantId: selectedAssistantId.value ? Number(selectedAssistantId.value) : null,
                        durationMinutes: duration,
                        resourceId: isResourceView.value ? doctorId : null,
                    })
                )
            );

            const bg = bgBatches.flat();

            if (reqId !== slotsReqId) return;
            availabilityBgEvents.value = bg;
        } catch (err) {
            console.error('Помилка оновлення фонових слотів:', err);
            availabilityBgEvents.value = [];
        } finally {
            if (reqId === slotsReqId) loadingSlots.value = false;
        }
    };

    // Booking functions
    const resetBooking = () => {
        booking.value = {
            start: null,
            end: null,
            patient_id: '',
            comment: '',
            waitlist_entry_id: '',
        };
    };

    const openBooking = (info) => {
        if (!info?.start || !info?.end) {
            console.warn('Booking open requested with incomplete slot info.', info);
        }
        booking.value = {
            start: info?.start || null,
            end: info?.end || null,
            patient_id: '',
            comment: '',
            waitlist_entry_id: '',
        };
        bookingError.value = null;
        isBookingOpen.value = true;
    };

    const closeBooking = () => {
        isBookingOpen.value = false;
        bookingLoading.value = false;
        bookingError.value = null;
        resetBooking();
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

    // Для QCalendarScheduler
    const selectAllow = async (info) => {
        try {
            if (!selectedDoctorId.value) return false;

            if (viewMode.value === 'dayGridMonth') return true;

            const startDate = formatDateYMD(info.start);
            const startTime = formatTimeHM(info.start);
            const duration = minutesDiff(info.start, info.end) || 30;

            let doctorId = selectedDoctorId.value;
            if (isResourceView.value && resourceViewType.value === 'doctor' && info.resource?.id) {
                doctorId = info.resource.id;
            }

            const slotRes = await fetchDoctorSlots({
                doctorId,
                date: startDate,
                procedureId: selectedProcedureId.value ? Number(selectedProcedureId.value) : null,
                roomId: selectedRoomId.value ? Number(selectedRoomId.value) : null,
                equipmentId: selectedEquipmentId.value ? Number(selectedEquipmentId.value) : null,
                assistantId: selectedAssistantId.value ? Number(selectedAssistantId.value) : null,
                durationMinutes: duration,
            });

            return slotRes.set.has(startTime);
        } catch {
            return true;
        }
    };

    const handleSelect = (info) => {
        if (isResourceView.value && info.resource?.id) {
            if (resourceViewType.value === 'doctor') {
                selectedDoctorId.value = String(info.resource.id);
            } else if (resourceViewType.value === 'room') {
                selectedRoomId.value = String(info.resource.id);
            }
        }

        openBooking(info);
    };

    const handleEventClick = (info) => {
        const appt = info.event.extendedProps?.appointment;
        if (!appt) {
            toastInfo('Це фонова подія або блок');
            return;
        }

        const patient = appt?.patient?.full_name || 'Пацієнт';
        const proc = appt?.procedure?.name || 'без процедури';
        const room = appt?.room?.name ? `, кабінет: ${appt.room.name}` : '';
        const eq = appt?.equipment?.name ? `, обладнання: ${appt.equipment.name}` : '';
        const asst = appt?.assistant?.full_name ? `, асистент: ${appt.assistant.full_name}` : '';

        const message = `${patient}\n${proc}${room}${eq}${asst}\nСтатус: ${appt?.status || 'заплановано'}`;

        toastInfo(message, { timeout: 5000 });
    };

    const handleEventDragStart = (payload) => {
        const event = payload?.event || payload;
        if (!event?.extendedProps?.appointment) {
            toastInfo('Перетягувати можна лише записи.');
        }
    };

    const handleEventDrop = async (payload) => {
        const event = payload?.event || payload;
        const appt = event?.extendedProps?.appointment;
        if (!appt) return;

        const nextStart = payload?.start || event?.start;
        const nextEnd = payload?.end || event?.end;
        if (!nextStart || !nextEnd) return;

        const startDate = fromQCalendarDateTime(nextStart);
        const endDate = fromQCalendarDateTime(nextEnd);
        if (!startDate || !endDate || isNaN(startDate.getTime()) || isNaN(endDate.getTime())) return;

        const resourceId = payload?.resource?.id || payload?.resourceId || event?.resourceId;
        const updatePayload = {
            start_at: startDate?.toISOString?.() || startDate,
            end_at: endDate?.toISOString?.() || endDate,
        };

        if (isResourceView.value && resourceViewType.value === 'doctor' && resourceId) {
            updatePayload.doctor_id = Number(resourceId);
        }

        if (isResourceView.value && resourceViewType.value === 'room') {
            if (resourceId === NO_ROOM_RESOURCE_ID) {
                updatePayload.room_id = null;
            } else if (resourceId) {
                updatePayload.room_id = Number(resourceId);
            }
        }

        try {
            await calendarApi.updateAppointment(appt.id, updatePayload);
            toastSuccess('Запис успішно перенесено');
            await refreshCalendar();
        } catch (error) {
            const message = error?.response?.data?.message || error?.message || 'Помилка перенесення запису';
            toastError(message);
        }
    };

    // Адаптований handleDatesSet для QCalendar
    const handleDatesSet = async (info) => {
        if (datesSetInFlight) return;
        datesSetInFlight = true;

        try {
            if (info?.start && info?.end) {
                const start = new Date(info.start);
                const end = new Date(info.end);

                const fromDate = formatDateYMD(start);
                const toDate = formatDateYMD(new Date(end.getTime() - 86400000));

                activeRange.value = { start, end, fromDate, toDate };
                if (diagnosticsEnabled.value) {
                    console.info('[Calendar activeRange]', activeRange.value);
                }
            } else {
                ensureRange();
            }

            if (info?.view) {
                qcalendarView.value = info.view;
            }

            await refreshCalendar();
        } catch (error) {
            console.error('Помилка в handleDatesSet:', error);
        } finally {
            datesSetInFlight = false;
        }
    };

    const refreshCalendar = async () => {
        const r = ensureRange();
        await Promise.all([
            loadEvents(r),
            loadCalendarBlocks(r),
            refreshAvailabilityBackground(r),
        ]);
    };

    const initialize = async () => {
        try {
            loading.value = true;
            error.value = null;

            await initAuth();
            await Promise.all([fetchDoctors(), fetchProcedures(), fetchClinics()]);

            syncSelectedDoctorWithClinic();
            updateDoctorSelectionMessage('initialize');

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
        syncSelectedDoctorWithClinic();
        logDiagnostics('selectedClinicId');
    });

    watch(selectedSpecializations, () => {
        syncSelectedDoctorWithClinic();
        logDiagnostics('selectedSpecializations');
    });

    watch(filteredDoctors, () => {
        syncSelectedDoctorWithClinic();
        logDiagnostics('filteredDoctors');
    });

    watch(selectedDoctorId, async () => {
        if (selectedDoctorId.value && !selectedDoctorIds.value.includes(String(selectedDoctorId.value))) {
            selectedDoctorIds.value = [...new Set([...selectedDoctorIds.value, String(selectedDoctorId.value)])];
        }
        logDiagnostics('selectedDoctorId');
        await refreshCalendar();
    });

    watch(selectedDoctorIds, async () => {
        if (isResourceView.value && resourceViewType.value === 'doctor') {
            if (!selectedDoctorIds.value.length) {
                syncSelectedDoctorWithClinic();
            }
            await refreshCalendar();
        }
        logDiagnostics('selectedDoctorIds');
    });

    watch(selectedRoomIds, async () => {
        if (isResourceView.value && resourceViewType.value === 'room') {
            await refreshCalendar();
        }
    });

    watch(rooms, () => {
        syncSelectedRooms();
    });

    watch(resourceViewType, async () => {
        if (resourceViewType.value === 'room') {
            syncSelectedRooms();
        } else {
            syncSelectedDoctorWithClinic();
        }
        logDiagnostics('resourceViewType');
        await refreshCalendar();
    });

    watch(clinicId, async () => {
        syncSelectedDoctorWithClinic();
        await Promise.all([fetchRooms(), fetchEquipments(), fetchAssistants(), fetchProcedures()]);
        await refreshCalendar();
        logDiagnostics('clinicId');
    });

    watch(
        [selectedClinicId, () => doctors.value.length, () => filteredDoctors.value.length],
        () => updateDoctorSelectionMessage('watch'),
    );

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

    // ========== ЕКСПОРТУЄМО ВСІ НЕОБХІДНІ ЗМІННІ ==========
    return {
        // Calendar data
        events,
        availabilityBgEvents,
        calendarBlocks,
        qcalendarView,
        activeRange,

        // UI state
        viewMode,
        selectedDoctorId,
        selectedDoctorIds,
        selectedProcedureId,
        selectedEquipmentId,
        selectedRoomId,
        selectedRoomIds,
        selectedAssistantId,
        selectedClinicId,
        selectedSpecializations,
        resourceViewType,
        isFollowUp,
        allowSoftConflicts,
        diagnosticsEnabled,
        doctorSelectionMessage,
        missingRoomAppointmentsCount,
        NO_ROOM_RESOURCE_ID,

        // Data collections
        doctors,
        filteredDoctors,
        procedures,
        rooms,
        equipments,
        assistants,
        clinics,
        specializations,
        diagnosticsSnapshot,

        // Computed properties (ЕКСПОРТУЄМО!)
        baseView, // ← ДОДАНО
        uiMode,   // ← ДОДАНО
        isResourceView,
        calendarView,
        showClinicSelector,

        // Loading states
        loading,
        loadingSlots,
        error,

        // Booking
        booking,
        isBookingOpen,
        bookingLoading,
        bookingError,

        // Functions
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
        handleEventDragStart,
        handleEventDrop,
        selectAllow,

        handleDatesSet,
        cleanup,

        // Utility functions
        formatDateYMD,
        formatTimeHM,
        toQCalendarDateTime,
        fromQCalendarDateTime,

        // Додаткові утилітні функції
        syncSelectedDoctorWithClinic,
        syncSelectedRooms,
        selectedDoctorResources,
        selectedRoomResources,
    };
}
