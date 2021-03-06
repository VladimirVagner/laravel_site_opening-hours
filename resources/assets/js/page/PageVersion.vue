<template>

    <div class="container">

        <div class="version-split row">
            <div class="version-cals col-sm-6 col-md-5 col-lg-4">
                <!-- Editing a calendar -->
                <calendar-editor v-if="$root.routeCalendar.events" :cal="$root.routeCalendar"></calendar-editor>

                <!-- Showing list of calendars -->
                <div v-else>
                    <div>
                        <h1>{{ version.label }}</h1>
                        <p>Van <strong>{{ formatDate(version.start_date) }}</strong>
                            tot <strong>{{ formatDate(version.end_date) }}</strong></p>

                        <button type="button" class="btn btn-default" @click="editVersion(version)"
                                :disabled="$root.isRecreatex">
                            Bewerk naam en geldigheidsperiode
                        </button>
                    </div>
                    <div>
                        <h2>Prioriteitenlijst uitzonderingen</h2>
                        <p>
                            De uren in de periode met de hoogste prioriteit bepalen de openingsuren voor de kalender.
                        </p>
                        <p v-if="this.calendars.length <= 10">
                            <button class="btn btn-primary" @click="addCalendar" v-if="reversedCalendars.length"
                                    :disabled="$root.isRecreatex">Voeg uitzonderingen toe
                            </button>
                        </p>
                        <div v-else class="alert alert-warning">
                            Er kan geen uitzondering toegevoegd worden.
                            <br>
                            (Max. 1 normale uren + 10 uitzonderingen)
                        </div>
                        <transition-group name="list" tag="div">
                            <div class="cal" v-if="cal.priority !== 0" v-for="(cal, index) in reversedCalendars"
                                 :key="cal.label">
                                <div class="cal-header">
                                    <div class="cal-info">
                                        <div class="cal-img" :class="'layer-'+cal.layer"></div>
                                        <div class="cal-name">
                                            <button class="btn btn-link"
                                                    @click="toCalendar($root.isRecreatex ? -1 : cal.id)">
                                                {{ cal.label }}
                                            </button>
                                            <span class="unpublished" v-if="!cal.published">Niet gepubliceerd</span>
                                        </div>
                                    </div>
                                    <div class="cal-lower cal-action"
                                         v-if="!$root.isRecreatex">
                                        <button class="btn btn-default" :disabled="index === reversedCalendars.length - 2"
                                                @click="swapLayers(cal.layer, cal.layer - 1)">
                                            lager
                                        </button>
                                    </div>
                                    <div class="cal-higher cal-action"
                                         v-if="!$root.isRecreatex">
                                        <button class="btn btn-default"
                                                :disabled="index === 0"
                                                @click="swapLayers(cal.layer, cal.layer + 1)">
                                            hoger
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </transition-group>
                        <h2>Basis openingsuren</h2>
                        <p>Op deze dagen is kanaal <strong>{{ channel.label }}</strong> normaal open.</p>
                        <div class="cal cal--one-line">
                            <div class="cal-header">
                                <div class="cal-info">
                                    <div class="cal-img" :class="'layer-'+ this.baseCalendar.layer"></div>
                                    <div class="cal-name">
                                        <button class="btn btn-link"
                                                @click="toCalendar($root.isRecreatex ? -1 :  baseCalendar.id)">
                                            {{ baseCalendar.label }}
                                        </button>
                                        <span class="unpublished" v-if="!baseCalendar.published">Niet gepubliceerd</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Encourage to add calendars after first one -->
                    <div class="text-center" v-if="reversedCalendars.length === 1 && !$root.isRecreatex">
                        <p style="padding-top:3em">
                            Je normale openingsuren zijn ingesteld.
                        </p>
                        <p>
                            Om vakantieperiodes, nationale feestdagen of andere uitzondering in te stellen, druk op "<a
                                href="#" @click.prevent="addCalendar">Voeg uitzonderingen toe</a>".
                        </p>
                    </div>

                    <!-- This should never happen -->
                    <div v-if="!reversedCalendars.length">
                        <p>
                            <button class="btn btn-link" @click="addCalendar" :disabled="$root.isRecreatex">
                                Voeg openingsuren toe
                            </button>
                        </p>
                    </div>
                </div>
            </div>
            <div class="version-preview col-sm-6 col-md-7 col-lg-8">
                <year-calendar :oh="layeredVersion" v-if="version.id"></year-calendar>
            </div>
        </div>
    </div>

</template>

<script>
    import YearCalendar from '../components/YearCalendar.vue'
    import CalendarEditor from '../components/CalendarEditor.vue'

    import {createCalendar, createFirstCalendar} from '../defaults.js'
    import {orderBy, Hub, toDatetime} from '../lib.js'

    export default {
        name: 'page-version',
        data() {
            return {
                tab: null,
                editing: 0,
                msg: 'Hello Vue!'
            }
        },
        computed: {
            service() {
                return this.$root.routeService || {};
            },
            channel() {
                return this.$root.routeChannel || {};
            },
            version() {
                return this.$root.routeVersion || {};
            },
            layeredVersion() {
                return Object.assign({}, this.version, {calendar: this.calendars});
            },
            calendars() {
                const calendars = (this.version.calendars || []);
                calendars.sort(orderBy('-priority'));
                return calendars.map(c => {
                    c.layer = -c.priority;
                    return c;
                })
            },
            baseCalendar() {
                return this.calendars.find((c) => {
                    return c.priority === 0
                }) || {}
            },
            reversedCalendars() {
                return inert(this.calendars).reverse();
            }
        },
        methods: {
            swapLayers(a, b) {
                a = this.calendars.find(c => c.layer === a);
                b = this.calendars.find(c => c.layer === b);
                if (a && b) {
                    const p = a.priority;
                    a.priority = b.priority;
                    b.priority = p;

                    Hub.$emit('createCalendar', a);
                    Hub.$emit('createCalendar', b);
                } else {
                    console.warn('one of the layers was not found', a, b);
                }
            },
            addCalendar() {
                if (this.calendars.length > 10) {
                    return alert('Er kan geen uitzondering toegevoegd worden.\n(Max. 1 normale uren + 10 uitzonderingen)');
                }

                const maxLayer = Math.max.apply(0, this.calendars.map(c => parseInt(c.layer)));

                const newCal = this.calendars.length ? createCalendar(maxLayer + 1) : createFirstCalendar(this.version);

                Hub.$emit('createCalendar', newCal);
            },
            formatDate(string) {

                if(!string) {
                    return 'DD-MM-YYYY';
                }
                return string.slice(8,10) + '-' + string.slice(5,7) + '-' + string.slice(0,4);
            }
        },
        components: {
            CalendarEditor,
            YearCalendar
        }
    }
</script>
