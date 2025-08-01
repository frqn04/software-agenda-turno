function pacientes() {
    return {
        list: [],
        editingItem: null,

        init() {
            this.loadPacientes();
        },

        async loadPacientes() {
            try {
                const response = await api.get('/pacientes');
                this.list = response.data || response;
            } catch (error) {
                showAlert('Error cargando pacientes: ' + error.message, 'error');
            }
        },

        openModal(paciente = null) {
            this.editingItem = paciente;
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" x-data="pacienteModal">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="isEditing ? 'Editar Paciente' : 'Nuevo Paciente'"></h3>
                        <form @submit.prevent="save">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                    <input type="text" x-model="form.nombre" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Apellido</label>
                                    <input type="text" x-model="form.apellido" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">DNI</label>
                                    <input type="text" x-model="form.dni" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Fecha Nacimiento</label>
                                    <input type="date" x-model="form.fecha_nacimiento" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Sexo</label>
                                <select x-model="form.sexo" required
                                        class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="">Seleccionar</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input type="text" x-model="form.telefono"
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" x-model="form.email"
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Dirección</label>
                                <textarea x-model="form.direccion" rows="2"
                                         class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                                <textarea x-model="form.observaciones" rows="3"
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

        async deletePaciente(id) {
            const result = await Swal.fire({
                title: '¿Estás seguro?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    await api.delete(`/pacientes/${id}`);
                    showAlert('Paciente eliminado exitosamente', 'success');
                    this.loadPacientes();
                } catch (error) {
                    showAlert('Error eliminando paciente: ' + error.message, 'error');
                }
            }
        }
    };
}

function pacienteModal() {
    return {
        isEditing: false,
        form: {
            nombre: '',
            apellido: '',
            dni: '',
            fecha_nacimiento: '',
            sexo: '',
            telefono: '',
            email: '',
            direccion: '',
            observaciones: '',
            activo: true
        },

        init() {
            const pacientesEl = document.querySelector('[x-data*="pacientes"]');
            const editingItem = Alpine.$data(pacientesEl).editingItem;
            
            if (editingItem) {
                this.isEditing = true;
                Object.assign(this.form, editingItem);
            }
        },

        async save() {
            try {
                if (this.isEditing) {
                    await api.put(`/pacientes/${this.form.id}`, this.form);
                    showAlert('Paciente actualizado exitosamente', 'success');
                } else {
                    await api.post('/pacientes', this.form);
                    showAlert('Paciente creado exitosamente', 'success');
                }
                
                this.closeModal();
                
                // Reload pacientes list
                const pacientesEl = document.querySelector('[x-data*="pacientes"]');
                if (pacientesEl) {
                    Alpine.$data(pacientesEl).loadPacientes();
                }
            } catch (error) {
                showAlert('Error guardando paciente: ' + error.message, 'error');
            }
        },

        closeModal() {
            document.getElementById('modals-container').innerHTML = '';
        }
    };
}

function doctores() {
    return {
        list: [],
        especialidades: [],
        editingItem: null,

        init() {
            this.loadDoctores();
            this.loadEspecialidades();
        },

        async loadDoctores() {
            try {
                const response = await api.get('/doctores');
                this.list = response.data || response;
            } catch (error) {
                showAlert('Error cargando doctores: ' + error.message, 'error');
            }
        },

        async loadEspecialidades() {
            try {
                const response = await api.get('/especialidades');
                this.especialidades = response.data || response;
            } catch (error) {
                showAlert('Error cargando especialidades: ' + error.message, 'error');
            }
        },

        openModal(doctor = null) {
            this.editingItem = doctor;
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" x-data="doctorModal">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="isEditing ? 'Editar Doctor' : 'Nuevo Doctor'"></h3>
                        <form @submit.prevent="save">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                    <input type="text" x-model="form.nombre" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Apellido</label>
                                    <input type="text" x-model="form.apellido" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Especialidad</label>
                                <select x-model="form.especialidad_id" required
                                        class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                    <option value="">Seleccionar Especialidad</option>
                                    <template x-for="especialidad in especialidades" :key="especialidad.id">
                                        <option :value="especialidad.id" x-text="especialidad.nombre"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" x-model="form.email" required
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input type="text" x-model="form.telefono"
                                           class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Matrícula</label>
                                <input type="text" x-model="form.matricula" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2">
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

        async deleteDoctor(id) {
            const result = await Swal.fire({
                title: '¿Estás seguro?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    await api.delete(`/doctores/${id}`);
                    showAlert('Doctor eliminado exitosamente', 'success');
                    this.loadDoctores();
                } catch (error) {
                    showAlert('Error eliminando doctor: ' + error.message, 'error');
                }
            }
        }
    };
}

function doctorModal() {
    return {
        isEditing: false,
        especialidades: [],
        form: {
            nombre: '',
            apellido: '',
            especialidad_id: '',
            email: '',
            telefono: '',
            matricula: '',
            activo: true
        },

        init() {
            const doctoresEl = document.querySelector('[x-data*="doctores"]');
            const editingItem = Alpine.$data(doctoresEl).editingItem;
            this.especialidades = Alpine.$data(doctoresEl).especialidades;
            
            if (editingItem) {
                this.isEditing = true;
                Object.assign(this.form, editingItem);
            }
        },

        async save() {
            try {
                if (this.isEditing) {
                    await api.put(`/doctores/${this.form.id}`, this.form);
                    showAlert('Doctor actualizado exitosamente', 'success');
                } else {
                    await api.post('/doctores', this.form);
                    showAlert('Doctor creado exitosamente', 'success');
                }
                
                this.closeModal();
                
                // Reload doctores list
                const doctoresEl = document.querySelector('[x-data*="doctores"]');
                if (doctoresEl) {
                    Alpine.$data(doctoresEl).loadDoctores();
                }
            } catch (error) {
                showAlert('Error guardando doctor: ' + error.message, 'error');
            }
        },

        closeModal() {
            document.getElementById('modals-container').innerHTML = '';
        }
    };
}

function turnos() {
    return {
        list: [],
        filterDate: new Date().toISOString().split('T')[0],

        init() {
            this.loadTurnos();
        },

        async loadTurnos() {
            try {
                const params = this.filterDate ? { fecha: this.filterDate } : {};
                const response = await api.get('/turnos', params);
                this.list = response.data || response;
            } catch (error) {
                showAlert('Error cargando turnos: ' + error.message, 'error');
            }
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('es-ES');
        },

        getStatusColor(estado) {
            switch (estado) {
                case 'programado': return 'bg-sky-100 text-sky-800';
                case 'realizado': return 'bg-emerald-100 text-emerald-800';
                case 'cancelado': return 'bg-rose-100 text-rose-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        },

        async cancelTurno(id) {
            const result = await Swal.fire({
                title: '¿Cancelar turno?',
                text: 'Esta acción marcará el turno como cancelado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'No cancelar'
            });

            if (result.isConfirmed) {
                try {
                    await api.patch(`/turnos/${id}/cancelar`);
                    showAlert('Turno cancelado exitosamente', 'success');
                    this.loadTurnos();
                } catch (error) {
                    showAlert('Error cancelando turno: ' + error.message, 'error');
                }
            }
        },

        async realizarTurno(id) {
            try {
                await api.patch(`/turnos/${id}/realizar`);
                showAlert('Turno marcado como realizado', 'success');
                this.loadTurnos();
            } catch (error) {
                showAlert('Error marcando turno: ' + error.message, 'error');
            }
        }
    };
}
