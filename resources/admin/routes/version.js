import Home from '../components/home'
import VersionList from '../components/version/list'
import VersionAdd from '../components/version/add'

/**
 * category 模块
 */
export default [
    {
        path: '/',
        component: Home,
        children: [
            {path: '/versions', component: VersionList, name: 'VersionList'},
            {path: '/version/add', component: VersionAdd, name: 'VersionAdd'},
        ]
    },
];