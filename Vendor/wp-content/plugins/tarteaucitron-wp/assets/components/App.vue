<template>
    <div id="app">
        <h1 class="wp-heading-inline">Services</h1>
        <form :action="action" method="post">
            <input type="hidden" name="action" value="tacwp_save_services">
            <table class="wp-list-table widefat" v-for="(serviceChunk, category) in services">
                <thead>
                <tr>
                    <th colspan="2">{{ category }}</th>
                </tr>
                </thead>
                <tbody v-for="service in serviceChunk"
                       :class="{open: service.active && Object.keys(service.options).length}">
                <tr>
                    <td>{{ service.label }}</td>
                    <td class="toggle-cell">
                        <input type="hidden" :name="`services[${service.name}][active]`"
                               :value="service.active ? 1 : 0">
                        <toggle-button :value="service.active"
                                       @change="toggleService(service, $event.value)"></toggle-button>
                    </td>
                </tr>
                <tr v-if="service.active && Object.keys(service.options).length" class="detail-row">
                    <td class="fields"
                        colspan="2">
                        <table class="form-table">
                            <tr v-for="(option, key) in service.options">
                                <th>
                                    <label :for="`${service.name.toLowerCase()}_${key}`">
                                        {{ option.label }}
                                    </label>
                                </th>
                                <td>
                                    <input type="text"
                                           :id="`${service.name.toLowerCase()}_${key}`"
                                           :name="`services[${service.name}][options][${key}]`"
                                           v-model="service.options[key].value"
                                           class="widefat"
                                           required>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                </tbody>
            </table>
            <div class="tablenav">
                <button type="submit" class="button button-primary button-large">Enregistrer</button>
            </div>
        </form>
    </div>
</template>

<script>
    export default {
        props: {
            dataServices: {
                type: Object,
                default() {
                    return {};
                }
            },
            action: {
                type: String,
                required: true,
            }
        },
        data() {
            return {
                services: this.dataServices,
            };
        },
        methods: {
            toggleService(service, active) {
                this.services[service.category].find(s => s.name === service.name).active = active;
            }
        }
    }
</script>

<style scoped>
    table:not(:last-child) {
        margin-bottom: 20px;
    }

    tbody:nth-child(odd) {
        background-color: #f9f9f9;
    }

    .detail-row td {
        padding-left: 48px;
    }

    .widefat td {
        font-size: 16px;
    }

    .toggle-cell {
        width: 100px;
    }

    .form-table th {
        width: 100px;
        font-weight: normal;
    }

    tbody.open > tr:first-child {
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    }
</style>