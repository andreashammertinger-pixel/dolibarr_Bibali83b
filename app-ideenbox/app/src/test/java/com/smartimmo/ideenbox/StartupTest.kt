package com.smartimmo.ideenbox

import androidx.test.core.app.ApplicationProvider
import androidx.work.testing.WorkManagerTestInitHelper
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.Robolectric
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/** App-Start auf allen unterstützten Android-Versionen */
@RunWith(RobolectricTestRunner::class)
@Config(sdk = [26, 28, 29, 30, 31, 33, 34, 35])
class StartupTest {
    @Test fun start() {
        // ohne Test-WorkManager wie auf dem Telefon
        Robolectric.buildActivity(MainActivity::class.java).setup().get()
    }
}
