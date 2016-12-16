import { Hub } from '../lib.js'

export const users = (window.initialUsers || []).map(expandUser)

export default {
  data() {
    return {
      // WARNING: all user data must be passed through expandUser()
      users
    }
  },
  computed: {
    routeUser () {
      return this.users.find(u => u.id === this.route.user) || {}
    }
  },
  methods: {
    fetchUsers(id) {
      return this.$http.get('/api/users')
        .then(({ data }) => {
          this.users = (data || []).map(expandUser)
        })
    }
  },
  mounted() {
    this.fetchUsers()
    Hub.$on('createRole', newRole => {
      if (!newRole.service_id) {
        newRole.service_id = this.routeService.id
      }
      newRole.role = newRole.role || 'Member'
      newRole.user_id = newRole.user_id || newRole.id
      if (!newRole.user_id && !newRole.email) {
        // Cannot continue without at least one of these
        return console.error('createRole: email is missing')
      } else if (!newRole.user_id) {
        // Create the missing user based on user.email
        // After the creation, the role will be added too
        Hub.$emit('createUser', newRole)
        return
      }

      this.$http.post('/api/roles', newRole).then(() => {
        this.fetchServices()
        this.modalClose()
      }).catch(error => {
        console.warn(error)
      })
    })

    Hub.$on('fetchUser', newRole => {
      if (!newRole.service_id) {
        newRole.service_id = this.routeService.id
      }
      newRole.role = newRole.role || 'Member'
      newRole.user_id = newRole.user_id || newRole.id
      if (!newRole.user_id && !newRole.email) {
        // Cannot continue without at least one of these
        return console.error('createRole: email is missing')
      } else if (!newRole.user_id) {
        // Create the missing user based on user.email
        // After the creation, the role will be added too
        Hub.$emit('createUser', newRole)
        return
      }

      this.$http.post('/api/roles', newRole).then(() => {
        this.fetchServices()
        this.modalClose()
      }).catch(error => {
        console.warn(error)
      })
    })

    Hub.$on('createUser', newUser => {
      if (newUser.id) {
        return console.error('createRole: this user probably already exists')
      }
      if (!newUser.email) {
        return console.error('createRole: email is missing')
      }
      newUser.name = newUser.name || newUser.email

      this.$http.post('/api/users', newUser).then(({ data }) => {
        Object.assign(newUser, data)
        if (newUser.role) {
          Hub.$emit('createRole', newUser)
        } else {
          this.fetchServices()
        }
        this.modalClose()
      }).catch(error => {
        console.warn(error)
      })
    })
  }
}

export function expandUser (u) {
  u.roles = u.roles || []
  u.services = u.roles.map(r => r.service)

  u.role = {}
  for (var i = 0; i < u.roles.length - 1; i++) {
    u.role[u.roles[i].service] = u.roles[i].role
  }

  return u
}
