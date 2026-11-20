package com.smartimmo.ideenbox

import androidx.test.core.app.ActivityScenario
import androidx.test.ext.junit.runners.AndroidJUnit4
import org.junit.Test
import org.junit.runner.RunWith

/** App startet auf dem Gerät ohne Absturz */
@RunWith(AndroidJUnit4::class)
class StartOnDeviceTest {
    @Test fun start() {
        ActivityScenario.launch(MainActivity::class.java).use { s -> s.onActivity { } }
    }
}
