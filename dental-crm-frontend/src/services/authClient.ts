import axios from 'axios'

const authClient = axios.create({
  baseURL: 'http://localhost/api',
  withCredentials: true
})

export default authClient
