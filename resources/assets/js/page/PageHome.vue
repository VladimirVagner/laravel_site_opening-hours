<template>
  <div class="container">
    <h1 v-if="isAdmin" v-text="">Admin</h1>
    <h1 v-else-if="isEditor">Alle diensten</h1>
    <h1 v-else>Mijn diensten</h1>
    <div>
      <span v-if="isAdmin">
        <span v-if="!draft" class="btn-group">
          <button type="button" class="btn btn-primary" :class="{ 'active': !route.tab }" @click="route.tab=0">Beheer diensten</button>
          <button type="button" class="btn btn-primary" :class="{ 'active': route.tab=='users' }" @click="route.tab='users'">Beheer gebruikers</button>
        </span>
        <button type="button" class="btn btn-primary" @click="newUser" v-if="route.tab == 'users'">+ Gebruiker uitnodigen</button>
        <button type="button" class="btn btn-primary" @click="draft = !draft" v-if="!route.tab">
            {{draft ? "Terug naar actieve diensten" : "+ Activeer diensten"}}
        </button>
      </span>
      <span v-else-if="!isEditor">
        <button type="button" class="btn btn-default" @click="requestService">Vraag toegang tot een dienst</button>
      </span>
      <form class="pull-right">
          <div class="form-group">
              <input aria-label="'Zoek ' + (route.tab ? 'gebruikers' : (draft ? 'inactive':'actieve') + ' diensten')"
                     v-model="query"
                     v-if="this.showSearch"
                     @input="route.offset=0"
                     class="form-control"
                     :placeholder="'Zoek ' + (route.tab ? 'gebruikers' : (draft ? 'inactive':'actieve') + ' diensten')"
                     style="max-width:300px"
                     type="search">
          </div>
      </form>
    </div>

    <!-- Users -->
    <div v-if="isAdmin && route.tab==='users'">
      <div v-if="!users.length" class="table-message">
        <h3 class="text-muted">
          Er zijn nog geen gebruikers op het platform. Mogelijke oorzaken:
          <br>Je hebt niet genoeg rechten.
          <br>Er liep iets fout.
        </h3>
        <p>
          <button class="btn btn-primary btn-lg" @click="newRole(srv)">Nodig een gebruiker uit</button>
        </p>
      </div>
      <div v-if="users.length&&!filteredUsers.length" class="table-message">
        <h1>Deze zoekopdracht leverde geen resultaten op</h1>
      </div>
      <table v-if="filteredUsers.length" class="table table-hover table-user">
        <thead>
          <tr>
            <th-sort by="name">Naam gebruiker</th-sort>
            <th-sort by="email">E-mailadres</th-sort>
            <th-sort by="services">Rollen</th-sort>
            <th-sort by="verified">Actief</th-sort>
            <th class="text-right">Verwijder</th>
          </tr>
        </thead>
        <tbody>
        <tr is="row-user" v-for="u in pagedUsers" :u="u"></tr>
        </tbody>
      </table>
      <pagination :total="filteredUsers.length"></pagination>
    </div>

    <!-- Services -->
    <div v-else>
      <div v-if="!allowedServices.length" class="table-message">
        <h3 class="text-muted" v-if="isAdmin && !draft">Er zijn geen actieve diensten.</h3>
        <h3 class="text-muted" v-else-if="isAdmin && draft">Er zijn geen inactieve diensten.</h3>
        <h3 class="text-muted" v-else>Je hebt nog geen toegang tot diensten</h3>

          <button type="button" class="btn btn-primary btn-lg" @click="draft = !draft" v-if="isAdmin">
              {{draft ? "Terug naar active diensten" : "Toon de inactieve diensten"}}
          </button>
      </div>
      <div v-else-if="!filteredServices.length" class="table-message">
        <h1>Deze zoekopdracht leverde geen resultaten op</h1>
      </div>
      <table v-else-if="draft" class="table table-service-admin">
        <thead>
          <tr>
            <th class="narrow">Activeer</th>
            <th-sort by="label">Naam dienst</th-sort>
            <th-sort class="narrow" by="source">Bron</th-sort>
          </tr>
        </thead>
        <tbody >
          <tr is="row-service-draft" v-for="s in pagedServices" :s="s"></tr>
        </tbody>
      </table>
      <table v-else-if="isAdmin" class="table table-hover table-service-admin">
        <thead>
          <tr>
            <th-sort by="label">Naam dienst</th-sort>
            <th-sort class="narrow" by="source">Bron</th-sort>
            <th-sort by="status" class="narrow">Status</th-sort>
            <th-sort by="end_date" class="narrow">Verloopt op</th-sort>
            <th-sort class="narrow" by="updated_at">Aangepast</th-sort>
            <th class="text-right narrow">Deactiveer</th>
          </tr>
        </thead>
        <tbody>
        <tr is="row-service-admin" v-for="s in pagedServices" :s="s"></tr>
        </tbody>
      </table>
      <table v-else class="table table-hover table-service">
        <thead>
          <tr>
            <th-sort by="label">Naam dienst</th-sort>
            <th-sort class="narrow" by="source">Bron</th-sort>
            <th-sort by="status" class="narrow">Status</th-sort>
            <th-sort by="end_date" class="narrow">Verloopt op</th-sort>
            <th-sort class="narrow" by="updated_at">Aangepast</th-sort>
          </tr>
        </thead>
        <tbody>
        <tr is="row-service" v-for="s in pagedServices" :s="s"></tr>
        </tbody>
      </table>
      <pagination :total="filteredServices.length"></pagination>
    </div>
  </div>
</template>

<script>
import { pageSize, default as Pagination } from '../components/Pagination.vue'
import RowService from '../components/RowService.vue'
import RowServiceAdmin from '../components/RowServiceAdmin.vue'
import RowServiceDraft from '../components/RowServiceDraft.vue'
import RowUser from '../components/RowUser.vue'
import ThSort from '../components/ThSort.vue'

import { orderBy } from '../lib.js'

export default {
  name: 'home',
  props: ['services', 'users'],
  data () {
    return {
      draft: false,
      order: 'name',
      query: ''
    }
  },
  computed: {

    // Search
    showSearch () {
      return (this.route.tab === "users" && (this.pagedUsers.length !== 0 || this.query !== '')) ||
          (this.pagedServices.length !== 0 || this.query !== '');
    },

    // Services
    allowedServices () {
      return this.services.filter(s => s.draft == this.draft)
    },
    filteredServices () {
      return this.query ? this.allowedServices.filter(s => (s.label || '').toLowerCase().indexOf(this.query.toLowerCase()) !== -1) : this.allowedServices
    },
    sortedServices () {
      return this.order ? this.filteredServices.sort(orderBy(this.order)) : this.filteredServices;
    },
    pagedServices () {
        let result = this.sortedServices.slice(this.route.offset || 0, this.route.offset + pageSize);
        // if only one page remains: show rows for that page.
        if (result.length === 0 && this.route.offset === pageSize) {
            result = this.sortedServices;
        }
        return result;
    },

    // Users
    filteredUsers () {
      return this.query ? this.users.filter(u => (u.name || '').indexOf(this.query) !== -1) : this.users
    },
    sortedUsers () {
      return this.order ? this.filteredUsers.slice().sort(orderBy(this.order)) : this.filteredUsers
    },
    pagedUsers () {
      return this.sortedUsers.slice(this.route.offset || 0, this.route.offset + pageSize)
    }
  },
  watch: {
    draft () {
      // Reset offset on tab change
      this.route.offset = 0
    }
  },
  components: {
    Pagination,
    RowService,
    RowServiceAdmin,
    RowServiceDraft,
    RowUser,
    ThSort
  }
}
</script>
