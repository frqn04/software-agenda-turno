function calendar() {
    return {
        selectedDate: new Date().toISOString().split('T')[0],
        selectedDoctor: '',
        doctores: [],
        morningSlots: [],
        afternoonSlots: [],
        turnos: [],

        init() {
            this.loadDoctores();
        },

        async loadDoctores() {
            try {
                const response = await api.get('/doctores');
                this.doctores = response.data || response;
            } catch (error) {
                showAlert('Error cargando doctores: ' + error.message, 'error');
            }
        },

        async loadAgenda() {
            if (!this.selectedDoctor || !this.selectedDate) {
                showAlert('Seleccione doctor y fecha', 'warning');
                return;
            }

            try {
                const response = await api.get('/turnos', {
                    doctor_id: this.selectedDoctor,
                    fecha: this.selectedDate
                });
                
                this.turnos = response.data || response;
                this.generateTimeSlots();
            } catch (error) {
                showAlert('Error cargando agenda: ' + error.message, 'error');
            }
        },

        generateTimeSlots() {
            this.morningSlots = [];
            this.afternoonSlots = [];

            // Morning slots: 08:00 - 12:00
            for (let hour = 8; hour < 12; hour++) {
                for (let minutes = 0; minutes < 60; minutes += 30) {
                    const time = `${hour.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                    const isOccupied = this.turnos.some(turno => 
                        turno.hora_inicio === time && turno.estado === 'programado'
                    );
                    
                    this.morningSlots.push({
                        time,
                        available: !isOccupied
                    });
                }
            }

            // Afternoon slots: 14:00 - 18:00
            for (let hour = 14; hour < 18; hour++) {
                for (let minutes = 0; minutes < 60; minutes += 30) {
                    const time = `${hour.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                    const isOccupied = this.turnos.some(turno => 
                        turno.hora_inicio === time && turno.estado === 'programado'
                    );
                    
                    this.afternoonSlots.push({
                        time,
                        available: !isOccupied
                    });
                }
            }
        },

        selectSlot(slot) {
            if (!slot.available) return;
            
            this.selectedSlot = slot;
            this.openAppointmentModal();
        },

        openAppointmentModal() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" x-data="appointmentModal">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Nuevo Turno</h3>
                        <form @submit.prevent="save">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Fecha</label>
                                <input type="date" x-model="form.fecha" required readonly
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Hora</label>
                                <input type="text" x-model="form.hora_inicio" required readonly
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Buscar Paciente (DNI o Nombre)</label>
                                <input type="text" x-model="searchTerm" @input="searchPacientes" 
                                       placeholder="Ingrese DNI o nombre del paciente"
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                <div x-show="pacientes.length > 0" class="mt-2 max-h-40 overflow-y-auto border border-gray-300 rounded">
                                    <template x-for="paciente in pacientes" :key="paciente.id">
                                        <div @click="selectPaciente(paciente)" 
                                             class="p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200">
                                            <span x-text="paciente.nombre + ' ' + paciente.apellido"></span>
                                            <span class="text-gray-500 text-sm" x-text="' (DNI: ' + paciente.dni + ')'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div x-show="form.paciente_id" class="mb-4 p-3 bg-gray-50 rounded">
                                <p class="text-sm"><strong>Paciente seleccionado:</strong></p>
                                <p x-text="selectedPacienteName"></p>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Motivo</label>
                                <textarea x-model="form.motivo" rows="3"
                                         class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" @click="closeModal" 
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                                    Cancelar
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700">
                                    Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            document.getElementById('modals-container').appendChild(modal);
            Alpine.initTree(modal);
        },

        async generatePDF() {
            try {
                const url = `${API_BASE}/agenda/pdf?doctor_id=${this.selectedDoctor}&fecha=${this.selectedDate}`;
                window.open(url, '_blank');
            } catch (error) {
                showAlert('Error generando PDF: ' + error.message, 'error');
            }
        }
    };
}

function appointmentModal() {
    return {
        form: {
            fecha: Alpine.store('calendar').selectedDate,
            hora_inicio: '',
            doctor_id: '',
            paciente_id: '',
            motivo: ''
        },
        searchTerm: '',
        pacientes: [],
        selectedPacienteName: '',

        init() {
            const calendarStore = Alpine.store('calendar');
            this.form.fecha = calendarStore.selectedDate;
            this.form.hora_inicio = calendarStore.selectedSlot?.time || '';
            this.form.doctor_id = calendarStore.selectedDoctor;
        },

        async searchPacientes() {
            if (this.searchTerm.length < 2) {
                this.pacientes = [];
                return;
            }

            try {
                const response = await api.get('/pacientes', { search: this.searchTerm });
                this.pacientes = response.data || response;
            } catch (error) {
                console.error('Error searching pacientes:', error);
            }
        },

        selectPaciente(paciente) {
            this.form.paciente_id = paciente.id;
            this.selectedPacienteName = `${paciente.nombre} ${paciente.apellido} (DNI: ${paciente.dni})`;
            this.pacientes = [];
            this.searchTerm = '';
        },

        async save() {
            try {
                await api.post('/turnos', this.form);
                showAlert('Turno creado exitosamente', 'success');
                this.closeModal();
                
                // Reload calendar
                const calendarEl = document.querySelector('[x-data*="calendar"]');
                if (calendarEl) {
                    Alpine.$data(calendarEl).loadAgenda();
                }
            } catch (error) {
                showAlert('Error creando turno: ' + error.message, 'error');
            }
        },

        closeModal() {
            document.getElementById('modals-container').innerHTML = '';
        }
    };
}
