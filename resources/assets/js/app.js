import './bootstrap';
import { createApp, compile, registerRuntimeCompiler } from 'vue';

// Register the runtime template compiler so Vue can process in-DOM templates
// (page HTML mounted on #root). Without this, the compiler is tree-shaken out
// of the production bundle because @vue/runtime-dom declares sideEffects:false.
registerRuntimeCompiler(compile);
import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';
import UIKitAlert from './components/UIKitAlert.vue';
import UIKitModal from './components/UIKitModal.vue';
import TaskRow from './tasks/components/TaskRow.vue';
import TaskType from './tasks/components/TaskType.vue';
import TaskOutput from './tasks/components/TaskOutput.vue';
import StatusButton from './tasks/components/StatusButton.vue';
import ExecuteButton from './tasks/components/ExecuteButton.vue';
import ImportButton from './tasks/components/ImportButton.vue';
import CommandList from './tasks/components/CommandList.vue';
import ClickToClose from './components/ClickToClose.vue';
import UpcomingCalendar from './tasks/components/UpcomingCalendar.vue';

UIkit.use(Icons);

const app = createApp({});

app.component('uikit-alert', UIKitAlert);
app.component('uikit-modal', UIKitModal);
app.component('status-button', StatusButton);
app.component('execute-button', ExecuteButton);
app.component('import-button', ImportButton);
app.component('task-type', TaskType);
app.component('task-output', TaskOutput);
app.component('task-row', TaskRow);
app.component('click-to-close', ClickToClose);
app.component('command-list', CommandList);
app.component('upcoming-calendar', UpcomingCalendar);

app.mount('#root');
